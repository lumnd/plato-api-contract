<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Ir\Field;
use Lumnd\PlatoApiContract\Ir\Schema;

/**
 * The rule vocabulary of plato\security\validate.
 *
 * `validate` addresses a nested value the way an HTML form names one, so `user.name` of the
 * contract is `user[name]` here and the elements of `tags` are `tags[*]`. Constraints that a
 * contract can state are all carried across, including the ones a document would otherwise only
 * advertise: `in:` becomes an anchored regex_match rather than a comment, and a boolean is spelled
 * out as the pattern of the values `Input::flag()` will read.
 *
 * `scalar`, `list` and `map` are `validate`'s rules for the shape of a value, and `[*]` its name for
 * the elements of an array. They are what lets `items.*.sku` be enforced rather than documented, and
 * what stops a field declared as a list from arriving as a string: without them a rule set is run
 * against the leaves of whatever was sent, and passes.
 */
final class PlatoValidationRules implements ValidationRules
{
    /**
     * The spellings `Input::flag()` reads, and nothing else.
     *
     * `validate` has no boolean rule, so without this a body of `{"loud": "fasle"}` would pass and
     * arrive as false: a decision the caller never made, taken on their behalf. A pattern sees a
     * value as the string it casts to, where a JSON `true` is `'1'` and a JSON `false` is `''`, so
     * the empty string is admitted too. That costs nothing: a field that arrived empty is skipped
     * before any rule of it runs.
     */
    private const BOOLEAN = '/^(1|0|true|false|on|off|yes|no)?$/i';

    public function forField(Field $field): array
    {
        $rules = [];
        // What an upload is, plato decides: `upload::info()` hands over a structure the contract
        // has no vocabulary for, so the shape of a file is the one thing not asserted here.
        $this->collect($field->name, $this->schema($field), $field->required, $rules, $field->source !== 'file');

        return $rules;
    }

    public function validatorExpression(string $input, string $rules): string
    {
        return 'validate::make(' . $input . ', ' . $rules . ')';
    }

    public function failureStatement(string $validator, PlatoConfig $config): string
    {
        $exception = $config->exceptionFqn();

        // The application's own failure mechanism, so a refused request is rendered where every
        // other refusal of that application is rendered and this template states no envelope
        if ($exception !== null) {
            return 'throw ' . $exception . '::refuse(' . $validator . '->errors());';
        }

        // Nothing registered: the errors, at the status a body that failed its schema is refused
        // with. No application code, and no guess at what an application's status codes mean
        return "return resp::json(['errors' => " . $validator . '->errors()], 422);';
    }

    public function imports(): array
    {
        return ['plato\\http\\resp', 'plato\\security\\validate'];
    }

    /**
     * @param array<string, list<string>> $rules
     * @param bool $containers whether the declared shape of the value is asserted
     */
    private function collect(string $path, Schema $schema, bool $required, array &$rules, bool $containers): void
    {
        // The runtime validator handles the object as a whole, so `required` asks for the object
        // itself rather than every property in it. Descendant rules are expanded only after their
        // parent arrived; this lets a nullable object contain required properties without making
        // the object itself mandatory.
        if ($schema->type === 'object') {
            $object = $containers ? ['map'] : [];
            if ($required) {
                $object[] = 'required';
            }
            if ($object !== []) {
                $rules[$path] = $object;
            }

            foreach ($schema->properties as $name => $property) {
                $this->collect(
                    $path . '[' . $name . ']',
                    $property,
                    in_array($name, $schema->requiredProperties, true),
                    $rules,
                    $containers,
                );
            }

            return;
        }

        if ($schema->type === 'array') {
            $list = $containers ? ['list'] : [];
            if ($required) {
                $list[] = 'required';
            }
            if ($list !== []) {
                $rules[$path] = $list;
            }
            // Every element the request carries, whatever it holds: `Rules::expand()` names them
            // one by one, so an element that is a structure is checked the same way the body is.
            if ($schema->items !== null) {
                $this->collect($path . '[*]', $schema->items, false, $rules, $containers);
            }

            return;
        }

        $leaf = $this->leaf($schema, $required, $containers);
        if ($leaf !== []) {
            $rules[$path] = $leaf;
        }
    }

    /** @return list<string> */
    private function leaf(Schema $schema, bool $required, bool $containers): array
    {
        // One value, and a list is not one: without this the rules below would be run against the
        // elements of whatever arrived, pass, and let the projection answer '' for the field.
        $rules = $containers ? ['scalar'] : [];
        if ($required) {
            $rules[] = 'required';
        }

        if ($schema->type === 'integer') {
            $rules[] = 'integer';
        } elseif ($schema->type === 'number') {
            $rules[] = 'numeric';
        } elseif ($schema->type === 'boolean') {
            $rules[] = 'regex_match[' . self::BOOLEAN . ']';
        }

        $rules = array_merge($rules, match ($schema->format) {
            'email' => ['email'],
            'uri' => ['url'],
            'date' => ['date'],
            default => [],
        });

        if ($schema->minLength !== null) {
            $rules[] = 'minlength[' . $schema->minLength . ']';
        }
        if ($schema->maxLength !== null) {
            $rules[] = 'maxlength[' . $schema->maxLength . ']';
        }
        if ($schema->minimum !== null) {
            $rules[] = 'min[' . $schema->minimum . ']';
        }
        if ($schema->maximum !== null) {
            $rules[] = 'max[' . $schema->maximum . ']';
        }
        if ($schema->pattern !== null) {
            $rules[] = 'regex_match[' . $schema->pattern . ']';
        }

        $enum = $this->enum($schema->enum);
        if ($enum !== null) {
            $rules[] = 'regex_match[' . $enum . ']';
        }

        return $rules;
    }

    /**
     * The admitted values as one anchored pattern, so a declared `in:` is refused rather than
     * merely documented. Null is left to the field's own nullability.
     *
     * @param list<string|int|float|bool|null> $enum
     */
    private function enum(array $enum): ?string
    {
        $alternatives = [];
        foreach ($enum as $value) {
            if ($value === null) {
                continue;
            }
            $alternatives[] = preg_quote(is_bool($value) ? ($value ? '1' : '0') : (string) $value, '/');
        }

        return $alternatives === [] ? null : '/^(' . implode('|', $alternatives) . ')$/';
    }

    /** The field's own schema, or a flat one rebuilt from what the field carries. */
    private function schema(Field $field): Schema
    {
        if ($field->schema !== null) {
            return $field->schema;
        }

        return new Schema(
            type: $field->type === 'array' ? 'string' : $field->type,
            nullable: $field->nullable,
            format: $field->format,
            enum: $field->enum,
            default: $field->default,
            hasDefault: $field->hasDefault,
            minLength: $field->minLength,
            maxLength: $field->maxLength,
            minimum: $field->minimum,
            maximum: $field->maximum,
            pattern: $field->pattern,
        );
    }
}
