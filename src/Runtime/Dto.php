<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Runtime;

use BackedEnum;
use Lumnd\PlatoApiContract\Dsl\ApiField;
use Lumnd\PlatoApiContract\Exception\DtoMappingException;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

final class Dto
{
    /**
     * Project an array onto a DTO contract. Undeclared keys are ignored at every level.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $data
     * @return T
     */
    public static function fromArray(string $class, array $data): object
    {
        /** @var T */
        return self::hydrateObject($class, $data, self::shortName($class), []);
    }

    /**
     * Normalize only properties declared by the DTO contract and validate the complete object graph.
     *
     * @param class-string|null $expectedClass
     * @return array<string, mixed>
     */
    public static function toArray(object $dto, ?string $expectedClass = null): array
    {
        $class = $expectedClass ?? $dto::class;

        return self::normalizeObject($dto, $class, self::shortName($class), []);
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $data
     * @param list<class-string> $stack
     */
    private static function hydrateObject(string $class, array $data, string $path, array $stack): object
    {
        self::guardCircularType($class, $path, $stack);
        $parameters = self::parameters($class, $path);
        $arguments = [];
        $nextStack = [...$stack, $class];

        foreach ($parameters as $name => $parameter) {
            if (!array_key_exists($name, $data)) {
                if ($parameter->isDefaultValueAvailable()) {
                    continue;
                }
                throw new DtoMappingException(self::fieldPath($path, $name), 'Required field is missing.');
            }

            $arguments[$name] = self::hydrateValue(
                $data[$name],
                $parameter,
                self::fieldPath($path, $name),
                $nextStack,
            );
        }

        return new $class(...$arguments);
    }

    /** @param list<class-string> $stack */
    private static function hydrateValue(
        mixed $value,
        ReflectionParameter $parameter,
        string $path,
        array $stack,
    ): mixed {
        [$type, $nullable] = self::namedType($parameter->getType(), $path);
        if ($value === null) {
            if ($nullable) {
                return null;
            }
            throw self::typeError($path, $type, $value);
        }

        if ($type === 'array') {
            if (!is_array($value)) {
                throw self::typeError($path, 'list', $value);
            }
            if (!array_is_list($value)) {
                throw new DtoMappingException($path, 'Expected a list, got an associative array.');
            }

            $itemType = self::arrayItemType($parameter, $path);
            $items = [];
            foreach ($value as $index => $item) {
                $items[] = self::hydrateNamedValue($item, $itemType, $path . '[' . $index . ']', $stack);
            }
            return $items;
        }

        return self::hydrateNamedValue($value, $type, $path, $stack);
    }

    /** @param list<class-string> $stack */
    private static function hydrateNamedValue(mixed $value, string $type, string $path, array $stack): mixed
    {
        $valid = match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value),
            'bool' => is_bool($value),
            default => null,
        };
        if ($valid !== null) {
            if (!$valid) {
                throw self::typeError($path, $type, $value);
            }
            return $value;
        }

        if (enum_exists($type)) {
            if (!is_a($type, BackedEnum::class, true)) {
                throw new DtoMappingException($path, 'Only backed enums are supported: ' . $type . '.');
            }
            return self::hydrateEnum($value, $type, $path);
        }
        if (!class_exists($type)) {
            throw new DtoMappingException($path, 'Unsupported DTO type ' . $type . '.');
        }
        if ($value instanceof $type) {
            self::normalizeObject($value, $type, $path, $stack);
            return $value;
        }
        if (!is_array($value)) {
            throw self::typeError($path, $type . ' or array', $value);
        }
        if (array_is_list($value) && $value !== []) {
            throw new DtoMappingException($path, 'Expected an associative array for DTO ' . $type . '.');
        }

        /** @var array<string, mixed> $value */
        return self::hydrateObject($type, $value, $path, $stack);
    }

    /** @param class-string<BackedEnum> $class */
    private static function hydrateEnum(mixed $value, string $class, string $path): BackedEnum
    {
        $reflection = new ReflectionEnum($class);
        if ($value instanceof $class) {
            return $value;
        }

        $backingType = $reflection->getBackingType();
        if ($backingType === null) {
            throw new DtoMappingException($path, 'Only backed enums are supported: ' . $class . '.');
        }
        $backingTypeName = $backingType->getName();
        $valid = $backingTypeName === 'int' ? is_int($value) : is_string($value);
        if (!$valid) {
            throw self::typeError($path, $backingTypeName . ' backing value for ' . $class, $value);
        }

        $case = $class::tryFrom($value);
        if ($case === null) {
            throw new DtoMappingException($path, 'Unknown backing value for ' . $class . '.');
        }

        return $case;
    }

    /**
     * @param class-string $class
     * @param list<class-string> $stack
     * @return array<string, mixed>
     */
    private static function normalizeObject(object $dto, string $class, string $path, array $stack): array
    {
        if (!$dto instanceof $class) {
            throw self::typeError($path, $class, $dto);
        }

        self::guardCircularType($class, $path, $stack);
        $parameters = self::parameters($class, $path);
        $reflection = new ReflectionClass($class);
        $nextStack = [...$stack, $class];
        $normalized = [];

        foreach ($parameters as $name => $parameter) {
            $property = $reflection->getProperty($name);
            $normalized[$name] = self::normalizeValue(
                $property->getValue($dto),
                $parameter,
                self::fieldPath($path, $name),
                $nextStack,
            );
        }

        return $normalized;
    }

    /** @param list<class-string> $stack */
    private static function normalizeValue(
        mixed $value,
        ReflectionParameter $parameter,
        string $path,
        array $stack,
    ): mixed {
        [$type, $nullable] = self::namedType($parameter->getType(), $path);
        if ($value === null) {
            if ($nullable) {
                return null;
            }
            throw self::typeError($path, $type, $value);
        }

        if ($type === 'array') {
            if (!is_array($value)) {
                throw self::typeError($path, 'list', $value);
            }
            if (!array_is_list($value)) {
                throw new DtoMappingException($path, 'Expected a list, got an associative array.');
            }

            $itemType = self::arrayItemType($parameter, $path);
            $items = [];
            foreach ($value as $index => $item) {
                $items[] = self::normalizeNamedValue($item, $itemType, $path . '[' . $index . ']', $stack);
            }
            return $items;
        }

        return self::normalizeNamedValue($value, $type, $path, $stack);
    }

    /** @param list<class-string> $stack */
    private static function normalizeNamedValue(mixed $value, string $type, string $path, array $stack): mixed
    {
        $valid = match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value),
            'bool' => is_bool($value),
            default => null,
        };
        if ($valid !== null) {
            if (!$valid) {
                throw self::typeError($path, $type, $value);
            }
            return $value;
        }

        if (enum_exists($type)) {
            if (!is_a($type, BackedEnum::class, true)) {
                throw new DtoMappingException($path, 'Only backed enums are supported: ' . $type . '.');
            }
            if (!$value instanceof $type) {
                throw self::typeError($path, $type, $value);
            }
            return $value->value;
        }
        if (!class_exists($type)) {
            throw new DtoMappingException($path, 'Unsupported DTO type ' . $type . '.');
        }
        if (!is_object($value)) {
            throw self::typeError($path, $type, $value);
        }

        return self::normalizeObject($value, $type, $path, $stack);
    }

    /**
     * @param class-string $class
     * @return array<string, ReflectionParameter>
     */
    private static function parameters(string $class, string $path): array
    {
        if (!class_exists($class)) {
            throw new DtoMappingException($path, 'DTO class does not exist: ' . $class . '.');
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isFinal() || !$reflection->isReadOnly()) {
            throw new DtoMappingException($path, 'DTO class must be final readonly: ' . $class . '.');
        }

        $constructor = $reflection->getConstructor();
        $publicProperties = array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property): bool => !$property->isStatic(),
        );
        if ($constructor === null) {
            if ($publicProperties !== []) {
                throw new DtoMappingException($path, 'DTO properties must use constructor promotion: ' . $class . '.');
            }
            return [];
        }
        if (!$constructor->isPublic()) {
            throw new DtoMappingException($path, 'DTO constructor must be public: ' . $class . '.');
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!$parameter->isPromoted()) {
                throw new DtoMappingException($path, 'DTO constructor parameter must promote a property: $' . $name . '.');
            }
            $property = $reflection->getProperty($name);
            if (!$property->isPublic() || $property->isStatic()) {
                throw new DtoMappingException($path, 'DTO property must be public: $' . $name . '.');
            }
            if ($parameter->getType() === null) {
                throw new DtoMappingException($path, 'DTO property must declare a type: $' . $name . '.');
            }
            $parameters[$name] = $parameter;
        }

        if (count($parameters) !== count($publicProperties)) {
            throw new DtoMappingException($path, 'Every public DTO property must be constructor-promoted.');
        }

        return $parameters;
    }

    private static function arrayItemType(ReflectionParameter $parameter, string $path): string
    {
        $declaringClass = $parameter->getDeclaringClass();
        if ($declaringClass === null) {
            throw new DtoMappingException($path, 'Cannot inspect the DTO property declaring class.');
        }
        $property = $declaringClass->getProperty($parameter->getName());
        $attributes = $property->getAttributes(ApiField::class);
        if (count($attributes) > 1) {
            throw new DtoMappingException($path, 'Only one ApiField attribute may be used.');
        }

        $metadata = $attributes === [] ? null : $attributes[0]->newInstance();
        if ($metadata?->items === null) {
            throw new DtoMappingException($path, 'Array DTO properties require ApiField(items: ...).');
        }

        return $metadata->items;
    }

    /** @return array{string, bool} */
    private static function namedType(?ReflectionType $type, string $path): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName(), $type->allowsNull()];
        }
        if (!$type instanceof ReflectionUnionType) {
            throw new DtoMappingException($path, 'Intersection DTO property types are not supported.');
        }

        $names = [];
        $nullable = false;
        foreach ($type->getTypes() as $member) {
            if (!$member instanceof ReflectionNamedType) {
                throw new DtoMappingException($path, 'Intersection DTO property types are not supported.');
            }
            if ($member->getName() === 'null') {
                $nullable = true;
            } else {
                $names[] = $member->getName();
            }
        }
        if (count($names) !== 1) {
            throw new DtoMappingException($path, 'Only nullable unions such as string|null are supported.');
        }

        return [$names[0], $nullable];
    }

    /**
     * @param class-string $class
     * @param list<class-string> $stack
     */
    private static function guardCircularType(string $class, string $path, array $stack): void
    {
        if (in_array($class, $stack, true)) {
            throw new DtoMappingException($path, 'Circular DTO type reference involving ' . $class . '.');
        }
    }

    /** @param class-string $class */
    private static function shortName(string $class): string
    {
        return (new ReflectionClass($class))->getShortName();
    }

    private static function fieldPath(string $path, string $field): string
    {
        return $path . '.' . $field;
    }

    private static function typeError(string $path, string $expected, mixed $value): DtoMappingException
    {
        return new DtoMappingException($path, 'Expected ' . $expected . ', got ' . get_debug_type($value) . '.');
    }
}
