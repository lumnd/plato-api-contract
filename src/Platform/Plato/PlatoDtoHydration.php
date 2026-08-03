<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use LogicException;
use Lumnd\PlatoApiContract\Dsl\ApiField;
use Lumnd\PlatoApiContract\Ir\Operation;
use Lumnd\PlatoApiContract\Ir\Schema;
use Lumnd\PlatoApiContract\Php\PhpExport;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

/**
 * How the values a controller read become the request Logic receives, and the response it answers.
 *
 * Both contract forms project out of the read input rather than out of the validator. A validator
 * says yes or no; it does not say what an absent optional field becomes, and
 * `validate::validated()` in particular returns only the fields that carried a rule and drops every
 * null - so a field with nothing to check, or with nothing sent, would never reach Logic at all.
 * Validation still runs first and still refuses bad input; this decides what the accepted input is,
 * so every declared field arrives, in its declared type, with its declared default.
 */
final class PlatoDtoHydration implements DtoHydration
{
    /** Four spaces of PHP, one level. */
    private const INDENT = '    ';

    public function request(Operation $operation, string $input): string
    {
        if ($operation->requestClass !== null) {
            return $this->construct($operation->requestClass, $input);
        }

        $entries = [];
        foreach ($operation->requestFields as $field) {
            $schema = $field->schema ?? new Schema($field->type, nullable: $field->nullable);
            $entries[$field->name] = $this->read($schema, $input, $field->name, $field->required, 2, 0);
        }

        return $this->literal($entries, 2);
    }

    public function response(Operation $operation, string $response): string
    {
        $class = $operation->primaryResponse()->dataClass;
        if ($class === null) {
            return $response;
        }

        return 'Dto::toArray(' . $response . ', \\' . ltrim($class, '\\') . '::class)';
    }

    public function imports(Operation $operation): array
    {
        $imports = ['Lumnd\\PlatoApiContract\\Runtime\\Input'];
        if ($operation->primaryResponse()->dataClass !== null) {
            $imports[] = 'Lumnd\\PlatoApiContract\\Runtime\\Dto';
        }

        return $imports;
    }

    /**
     * One declared value, read out of `$data` under `$key`.
     *
     * `$indent` is how deep the expression is written; `$depth` only numbers the `$item` variables
     * of nested array_map() calls, so that an array of arrays does not shadow its own element.
     */
    private function read(
        Schema $schema,
        string $data,
        string $key,
        bool $required,
        int $indent,
        int $depth,
    ): string {
        $name = var_export($key, true);
        $absent = !$required && !$schema->hasDefault;

        return match ($schema->type) {
            'object' => $this->orNull(
                $absent,
                $data,
                $name,
                $this->object($schema, 'Input::map(' . $data . ', ' . $name . ')', $indent + 1, $depth),
            ),
            'array' => $this->orNull(
                $absent,
                $data,
                $name,
                $this->elements($schema, 'Input::items(' . $data . ', ' . $name . ')', $indent, $depth),
            ),
            default => $this->scalar($schema, 'Input::at(' . $data . ', ' . $name . ')', $required),
        };
    }

    /** A structure that may be absent answers null rather than an empty one. */
    private function orNull(bool $absent, string $data, string $name, string $expression): string
    {
        return $absent
            ? 'Input::at(' . $data . ', ' . $name . ') === null ? null : ' . $expression
            : $expression;
    }

    private function scalar(Schema $schema, string $value, bool $required): string
    {
        $default = match (true) {
            $schema->hasDefault => ', ' . var_export($schema->default, true),
            !$required => ', null',
            default => '',
        };

        return match ($schema->type) {
            'integer' => 'Input::integer(' . $value . $default . ')',
            'number' => 'Input::decimal(' . $value . $default . ')',
            'boolean' => 'Input::flag(' . $value . $default . ')',
            default => 'Input::text(' . $value . $default . ')',
        };
    }

    private function object(Schema $schema, string $data, int $indent, int $depth): string
    {
        $entries = [];
        foreach ($schema->properties as $name => $property) {
            $entries[$name] = $this->read(
                $property,
                $data,
                $name,
                in_array($name, $schema->requiredProperties, true),
                $indent,
                $depth,
            );
        }

        return $this->literal($entries, $indent);
    }

    private function elements(Schema $schema, string $items, int $indent, int $depth): string
    {
        $element = $schema->items ?? new Schema('string');
        $item = '$item' . ($depth === 0 ? '' : (string) $depth);

        $expression = match ($element->type) {
            'object' => $this->object($element, $item, $indent + 1, $depth + 1),
            'array' => $this->elements($element, 'Input::each(' . $item . ')', $indent, $depth + 1),
            default => $this->scalar($element, $item, !$element->nullable),
        };

        return 'array_map(static fn (mixed ' . $item . '): ' . $this->phpType($element)
            . ' => ' . $expression . ', ' . $items . ')';
    }

    private function phpType(Schema $schema): string
    {
        $type = match ($schema->type) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array', 'object' => 'array',
            default => 'string',
        };

        return $schema->nullable && $type !== 'array' ? '?' . $type : $type;
    }

    /**
     * @param array<string, string> $entries
     * @param int $indent how deep the entries sit, in levels of four spaces
     */
    private function literal(array $entries, int $indent): string
    {
        if ($entries === []) {
            return '[]';
        }

        $inner = str_repeat(self::INDENT, $indent + 1);
        $lines = [];
        foreach ($entries as $name => $expression) {
            $lines[] = $inner . var_export($name, true) . ' => ' . $expression . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat(self::INDENT, $indent) . ']';
    }

    /**
     * @param class-string $class
     * @param int $depth how many array_map() calls the expression sits inside, so that an element
     *                   that is itself an array does not shadow the `$item` it is read from
     */
    private function construct(string $class, string $data, int $depth = 0): string
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getParameters() === []) {
            return 'new \\' . ltrim($class, '\\') . '()';
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $parameter->getName() . ': ' . $this->argument($parameter, $data, $depth);
        }

        return 'new \\' . ltrim($class, '\\') . '(' . implode(', ', $arguments) . ')';
    }

    private function argument(ReflectionParameter $parameter, string $data, int $depth): string
    {
        $name = var_export($parameter->getName(), true);
        $value = 'Input::at(' . $data . ', ' . $name . ')';
        [$type, $nullable] = $this->namedType($parameter->getType());
        $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        $optional = $nullable || $parameter->isDefaultValueAvailable();

        return match ($type) {
            'string' => 'Input::text(' . $value . $this->fallback($optional, $default) . ')',
            'int' => 'Input::integer(' . $value . $this->fallback($optional, $default) . ')',
            'float' => 'Input::decimal(' . $value . $this->fallback($optional, $default) . ')',
            'bool' => 'Input::flag(' . $value . $this->fallback($optional, $default) . ')',
            'array' => $this->arrayArgument($parameter, $data, $name, $nullable, $depth),
            default => $this->classArgument($type, $data, $name, $nullable, $depth),
        };
    }

    /**
     * An array property, read element by element and answering what the DTO says an absent one is.
     *
     * `#[ApiField(items: ...)]` is what the property means by an element, and it is the one
     * declaration the documented schema and the generated rules are written from too. An element is
     * therefore built the way the item class or the item scalar is declared - a `list<BasketLineReq>`
     * reaches Logic as `BasketLineReq` instances, which is what the Logic skeleton is typed with -
     * rather than as whatever the caller happened to post.
     *
     * `?array $tags = null` declares null, and both the constructor default and the documented
     * schema say so; casting it to an empty array here would be this class contradicting the class
     * it is building. A default array is used as written, and an array that declares neither is
     * empty because a request that omitted it carries no elements.
     */
    private function arrayArgument(
        ReflectionParameter $parameter,
        string $data,
        string $name,
        bool $nullable,
        int $depth,
    ): string {
        $value = 'Input::at(' . $data . ', ' . $name . ')';
        $hasDefault = $parameter->isDefaultValueAvailable();
        $default = $hasDefault ? $parameter->getDefaultValue() : null;
        $elements = $this->items($parameter, 'Input::items(' . $data . ', ' . $name . ')', $depth);

        if ($hasDefault ? $default === null : $nullable) {
            return '(' . $value . ' === null ? null : ' . $elements . ')';
        }
        if (is_array($default) && $default !== []) {
            return '(' . $value . ' === null ? ' . PhpExport::asArray($default, 0) . ' : ' . $elements . ')';
        }

        return $elements;
    }

    private function fallback(bool $optional, mixed $default): string
    {
        if (!$optional) {
            return '';
        }

        return ', ' . ($default === null ? 'null' : var_export($default, true));
    }

    /** The elements of one array property, each read as the item type that property declares. */
    private function items(ReflectionParameter $parameter, string $items, int $depth): string
    {
        $type = $this->itemType($parameter);
        $item = '$item' . ($depth === 0 ? '' : (string) $depth);
        $expression = $this->element($type, $item, $depth);

        return 'array_map(static fn (mixed ' . $item . '): ' . $this->itemPhpType($type)
            . ' => ' . $expression . ', ' . $items . ')';
    }

    private function element(string $type, string $item, int $depth): string
    {
        return match ($type) {
            'string' => 'Input::text(' . $item . ')',
            'int' => 'Input::integer(' . $item . ')',
            'float' => 'Input::decimal(' . $item . ')',
            'bool' => 'Input::flag(' . $item . ')',
            default => $this->classElement($type, $item, $depth),
        };
    }

    private function classElement(string $class, string $item, int $depth): string
    {
        if (enum_exists($class)) {
            return $this->enumFrom($class, $item);
        }
        if (!class_exists($class)) {
            throw new LogicException('Cannot generate hydration for DTO array element type: ' . $class);
        }

        return $this->construct($class, $item, $depth + 1);
    }

    private function itemPhpType(string $type): string
    {
        return match ($type) {
            'string', 'int', 'float', 'bool' => $type,
            default => '\\' . ltrim($type, '\\'),
        };
    }

    /** What one element of an array property is, which only #[ApiField(items: ...)] says. */
    private function itemType(ReflectionParameter $parameter): string
    {
        $declaring = $parameter->getDeclaringClass();
        $property = $declaring?->getProperty($parameter->getName());
        $attributes = $property?->getAttributes(ApiField::class) ?? [];
        $items = $attributes === [] ? null : $attributes[0]->newInstance()->items;

        if ($items === null) {
            throw new LogicException(
                'Array DTO properties require ApiField(items: ...): '
                    . ($declaring?->getName() ?? '?') . '::$' . $parameter->getName(),
            );
        }

        return $items;
    }

    private function classArgument(string $class, string $data, string $name, bool $nullable, int $depth): string
    {
        $value = 'Input::at(' . $data . ', ' . $name . ')';

        if (enum_exists($class)) {
            $expression = $this->enumFrom($class, $value);
        } elseif (class_exists($class)) {
            $expression = $this->construct($class, 'Input::map(' . $data . ', ' . $name . ')', $depth);
        } else {
            throw new LogicException('Cannot generate hydration for DTO property type: ' . $class);
        }

        return $nullable ? '(' . $value . ' === null ? null : ' . $expression . ')' : $expression;
    }

    /** @param class-string<UnitEnum> $class */
    private function enumFrom(string $class, string $value): string
    {
        $cast = (new ReflectionEnum($class))->getBackingType()?->getName() === 'int'
            ? 'Input::integer(' . $value . ')'
            : 'Input::text(' . $value . ')';

        return '\\' . ltrim($class, '\\') . '::from(' . $cast . ')';
    }

    /** @return array{string, bool} */
    private function namedType(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName(), $type->allowsNull()];
        }
        if (!$type instanceof ReflectionUnionType) {
            return ['mixed', false];
        }

        $names = [];
        foreach ($type->getTypes() as $member) {
            if (!$member instanceof ReflectionNamedType) {
                return ['mixed', false];
            }
            if ($member->getName() !== 'null') {
                $names[] = $member->getName();
            }
        }

        return [$names[0] ?? 'mixed', true];
    }
}
