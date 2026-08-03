<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use Lumnd\PlatoApiContract\Dsl\RuleSet;
use Lumnd\PlatoApiContract\Ir\Field;
use Lumnd\PlatoApiContract\Ir\Schema;

/**
 * A rule set becomes request fields or a response schema.
 *
 * Two readings of the same syntax, because the two sides answer different questions. A request says
 * what a caller may send and is validated, so every field has to state whether it may be absent -
 * `required`, `nullable`, or a `default:` - and the compiler refuses a field that states none. That
 * refusal is the whole point: a field whose presence is unstated is exactly the field that silently
 * fails to arrive. A response is projected rather than validated, so a field is present unless it
 * says `optional`, and rules that only make sense for input are refused outright.
 */
final class RuleSetCompiler
{
    /**
     * @param list<string> $segments the path parameters of the endpoint, in order
     * @return list<Field>
     *
     * @throws SchemaException
     */
    public function request(RuleSet $set, string $method, array $segments): array
    {
        $root = $this->tree($set);
        $indexes = array_flip($segments);
        $fields = [];

        foreach ($root->children as $name => $node) {
            $schema = $this->schema($node, true);
            $presence = $this->presence($node, true);
            [$source, $index] = $this->source($name, $node, $method, $indexes, $presence['required']);

            $fields[] = new Field(
                name: $name,
                source: $source,
                type: $schema->type,
                required: $presence['required'],
                nullable: $presence['nullable'],
                default: $schema->default,
                format: $schema->format,
                minLength: $schema->minLength,
                maxLength: $schema->maxLength,
                enum: $schema->enum,
                description: $schema->description,
                segmentIndex: $index,
                hasDefault: $schema->hasDefault,
                schema: $schema,
                minimum: $schema->minimum,
                maximum: $schema->maximum,
                pattern: $schema->pattern,
            );
        }

        foreach ($segments as $segment) {
            if (!isset($root->children[$segment])) {
                throw new SchemaException(
                    'rules.path_parameter_missing',
                    'Path parameter {' . $segment . '} has no rule of its own.',
                );
            }
        }

        return $fields;
    }

    /** @throws SchemaException */
    public function response(RuleSet $set): Schema
    {
        return $this->object($this->tree($set), false, true);
    }

    /** @throws SchemaException */
    private function tree(RuleSet $set): RuleNode
    {
        $root = new RuleNode('');

        foreach ($set->fields as $path => $rules) {
            $segments = explode('.', $path);
            if ($segments[0] === '*') {
                throw new SchemaException(
                    'rules.malformed',
                    'A rule set describes an object, so * cannot be a top level field: ' . $path,
                );
            }

            $node = $root;
            $walked = '';
            foreach ($segments as $segment) {
                $walked = $walked === '' ? $segment : $walked . '.' . $segment;
                $node = $segment === '*' ? $node->element($walked) : $node->child($segment, $walked);
            }

            $node->rules = FieldRules::parse($path, $rules);
        }

        return $root;
    }

    /** @throws SchemaException */
    private function schema(RuleNode $node, bool $request): Schema
    {
        $rules = $node->rules;
        $type = $this->type($node);

        if ($type === 'object') {
            return $this->object($node, $request, false);
        }

        $items = null;
        if ($type === 'array') {
            if ($node->items === null) {
                throw new SchemaException(
                    'rules.array_items_missing',
                    'An array field needs its elements declared as ' . $node->path . '.*',
                );
            }
            $items = $this->schema($node->items, $request);
        }

        $presence = $this->presence($node, $request);

        // `validate` reads false as nothing sent, so a required boolean could never arrive as false.
        if ($request && $type === 'boolean' && $presence['required'] && !str_ends_with($node->path, '.*')) {
            throw new SchemaException(
                'rules.boolean_required',
                'A boolean cannot be required, because false is indistinguishable from absent: give '
                    . $node->path . ' a default: or make it nullable.',
            );
        }

        return new Schema(
            type: $type,
            nullable: $presence['nullable'],
            format: $rules === null ? null : $rules->format,
            items: $items,
            enum: $rules === null ? [] : $rules->enum,
            description: $rules === null ? '' : $rules->description,
            default: $rules === null ? null : $rules->default,
            hasDefault: $rules !== null && $rules->hasDefault,
            minLength: $rules === null ? null : $rules->minLength,
            maxLength: $rules === null ? null : $rules->maxLength,
            minimum: $rules === null ? null : $rules->minimum,
            maximum: $rules === null ? null : $rules->maximum,
            pattern: $rules === null ? null : $rules->pattern,
        );
    }

    /**
     * @param bool $root the body itself, which is an object whether or not it declares a field
     *
     * @throws SchemaException
     */
    private function object(RuleNode $node, bool $request, bool $root): Schema
    {
        if (!$root && $node->children === []) {
            throw new SchemaException(
                'rules.object_properties_missing',
                'An object field needs its properties declared as ' . $node->path . '.<name>',
            );
        }

        $properties = [];
        $required = [];
        foreach ($node->children as $name => $child) {
            $properties[$name] = $this->schema($child, $request);
            if ($this->presence($child, $request)['required']) {
                $required[] = $name;
            }
        }

        return new Schema(
            type: 'object',
            nullable: !$root && $this->presence($node, $request)['nullable'],
            properties: $properties,
            description: $node->rules === null ? '' : $node->rules->description,
            requiredProperties: $required,
        );
    }

    /** @throws SchemaException */
    private function type(RuleNode $node): string
    {
        $structural = $node->structuralType();
        $declared = $node->rules?->typeDeclared === true ? $node->rules->type : null;

        if ($structural !== null && $declared !== null && $declared !== $structural) {
            throw new SchemaException(
                'rules.type_conflict',
                $node->path . ' is declared ' . $declared . ' but its rules describe ' . $structural . '.',
            );
        }

        return $structural ?? $declared ?? 'string';
    }

    /**
     * @return array{required: bool, nullable: bool}
     *
     * @throws SchemaException
     */
    private function presence(RuleNode $node, bool $request): array
    {
        $rules = $node->rules;

        // An element exists because it is in the array; only its nullability is open.
        if (str_ends_with($node->path, '.*')) {
            if ($rules !== null && ($rules->hasDefault || $rules->presence === 'optional')) {
                throw new SchemaException(
                    'rules.element_presence',
                    'An array element is always present, so ' . $node->path . ' takes neither optional nor default:.',
                );
            }

            return ['required' => true, 'nullable' => $rules?->presence === 'nullable'];
        }

        // Named only through its descendants. In a request it has to be there when something below
        // it has to be; in a response it is there, because a response field is unless it says
        // otherwise.
        if ($rules === null) {
            $required = !$request || $node->holdsRequired();

            return ['required' => $required, 'nullable' => $request && !$required];
        }

        if (!$request) {
            if ($rules->hasDefault || $rules->source !== null) {
                throw new SchemaException(
                    'rules.response_rule',
                    'A response is projected, not read, so ' . $node->path . ' takes neither default: nor from:.',
                );
            }

            return [
                'required' => $rules->presence !== 'optional',
                'nullable' => $rules->presence === 'nullable',
            ];
        }

        return match (true) {
            $rules->presence === 'required' => ['required' => true, 'nullable' => false],
            $rules->presence === 'nullable' => ['required' => false, 'nullable' => true],
            // `default:null` says the absent value is null, which makes the field nullable too;
            // anything else would document a default the declared type does not admit.
            $rules->hasDefault => ['required' => false, 'nullable' => $rules->default === null],
            default => throw new SchemaException(
                'rules.presence_undeclared',
                $node->path . ' must declare required, nullable or default:, so that what reaches Logic '
                    . 'when it is absent is stated rather than guessed.',
            ),
        };
    }

    /**
     * Where the field is read from, and which path segment it is when it is one.
     *
     * @param array<string, int> $indexes
     * @return array{string, ?int}
     *
     * @throws SchemaException
     */
    private function source(string $name, RuleNode $node, string $method, array $indexes, bool $required): array
    {
        $declared = $node->rules?->source;

        if (isset($indexes[$name])) {
            if ($declared !== null && $declared !== 'segment') {
                throw new SchemaException(
                    'rules.path_parameter_source',
                    $name . ' is a path parameter, so it cannot be read from ' . $declared . '.',
                );
            }
            if (!$required) {
                throw new SchemaException(
                    'rules.path_parameter_optional',
                    'Path parameter ' . $name . ' must be required.',
                );
            }

            return ['segment', $indexes[$name]];
        }

        if ($declared === 'segment') {
            throw new SchemaException(
                'rules.path_parameter_unknown',
                $name . ' is read from a path segment but the endpoint path does not name it.',
            );
        }

        $fallback = in_array(strtoupper($method), ['GET', 'DELETE'], true) ? 'query' : 'json';

        return [$declared ?? $fallback, null];
    }
}
