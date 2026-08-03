<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use InvalidArgumentException;
use Lumnd\PlatoApiContract\Dsl\ApiField;
use Lumnd\PlatoApiContract\Ir\Field;
use Lumnd\PlatoApiContract\Ir\Schema;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * The application's own readonly DTO classes are the contract, so the IR is reflected out of them.
 *
 * One promoted constructor property is one request field or one response property: the PHP type gives
 * the schema type, a constructor default makes it optional, and #[ApiField] plus the property docblock
 * add what PHP cannot express. There is no array schema DSL in between, so a DTO and its
 * documentation cannot disagree.
 */
final class DtoSchemaReflector
{
    public function __construct(
        private readonly PhpDocMetadata $phpDoc = new PhpDocMetadata(),
    ) {
    }

    /**
     * @param class-string $class
     * @param list<string> $segments the ordered path parameters of the endpoint
     * @return list<Field>
     */
    public function request(string $class, string $method, array $segments): array
    {
        $parameters = $this->parameters($class);
        $segmentIndexes = array_flip($segments);
        $fields = [];

        foreach ($parameters as $name => $parameter) {
            $property = $this->property($class, $parameter);
            $metadata = $this->metadata($property);
            $doc = $this->phpDoc->field($property->getDocComment(), $class . '::$' . $name);
            $source = $metadata?->source;

            if (isset($segmentIndexes[$name])) {
                if ($source !== null && $source !== 'segment') {
                    throw new SchemaException(
                        'dto.path_parameter_source',
                        $class . '::$' . $name . ' is a path parameter and cannot use source ' . $source . '.',
                    );
                }
                $source = 'segment';
            } elseif ($source === 'segment') {
                throw new SchemaException(
                    'dto.path_parameter_unknown',
                    $class . '::$' . $name . ' uses source segment but is absent from the endpoint path.',
                );
            }

            $source ??= in_array(strtoupper($method), ['GET', 'DELETE'], true) ? 'query' : 'json';
            if (!in_array($source, Field::SOURCES, true)) {
                throw new InvalidArgumentException($class . '::$' . $name . ' has unsupported source ' . $source . '.');
            }

            $schema = $this->schema($parameter->getType(), $metadata, [$class], true, $doc['description']);
            $required = $doc['must'] ?? !$parameter->isDefaultValueAvailable();
            if (!$required && !$parameter->isDefaultValueAvailable()) {
                throw new InvalidArgumentException(
                    $class . '::$' . $name . ' uses @must false but has no constructor default.',
                );
            }
            if ($source === 'segment' && !$required) {
                throw new SchemaException(
                    'dto.path_parameter_optional',
                    'Path parameter ' . $name . ' must be required.',
                );
            }
            if (!$required) {
                $schema = $schema->withDefault($parameter->getDefaultValue());
            }
            $this->assertBoolean($class . '::$' . $name, $schema, $required);

            try {
                $fields[] = new Field(
                    name: $name,
                    source: $source,
                    type: $schema->type,
                    required: $required,
                    nullable: $schema->nullable,
                    default: $schema->default,
                    format: $schema->format,
                    minLength: $schema->minLength,
                    maxLength: $schema->maxLength,
                    enum: $schema->enum,
                    description: $schema->description,
                    segmentIndex: $source === 'segment' ? (int) $segmentIndexes[$name] : null,
                    hasDefault: $schema->hasDefault,
                    schema: $schema,
                    minimum: $schema->minimum,
                    maximum: $schema->maximum,
                );
            } catch (InvalidArgumentException $exception) {
                throw new InvalidArgumentException(
                    $class . '::$' . $name . ': ' . $exception->getMessage(),
                );
            }
        }

        foreach ($segments as $segment) {
            if (!isset($parameters[$segment])) {
                throw new SchemaException(
                    'dto.path_parameter_missing',
                    'Path parameter {' . $segment . '} has no matching constructor property on ' . $class . '.',
                );
            }
        }

        return $fields;
    }

    /**
     * @param class-string $class
     */
    public function response(string $class): Schema
    {
        [$properties, $required] = $this->objectProperties($class, [$class], false);

        return new Schema(type: 'object', properties: $properties, requiredProperties: $required);
    }

    /**
     * A boolean a request insists on could never arrive as false.
     *
     * `validate` reads false as nothing sent - `required` is exactly `!is_empty()`, and false is
     * empty - so a required boolean turns a legitimate `false` into a 422. Rule sets refuse the
     * same declaration with `rules.boolean_required`; a DTO says it with a `bool` that has no
     * constructor default, and is refused here rather than generating a controller that cannot
     * accept half of the values its own type admits.
     *
     * @throws SchemaException
     */
    private function assertBoolean(string $path, Schema $schema, bool $required): void
    {
        if ($schema->type === 'object') {
            foreach ($schema->properties as $name => $property) {
                $this->assertBoolean(
                    $path . '.' . $name,
                    $property,
                    in_array($name, $schema->requiredProperties, true),
                );
            }

            return;
        }

        // An element is present by being in the array and carries no `required` of its own, but the
        // properties of one that is a structure do.
        if ($schema->type === 'array') {
            if ($schema->items !== null) {
                $this->assertBoolean($path . '.*', $schema->items, false);
            }

            return;
        }

        if ($schema->type !== 'boolean' || !$required) {
            return;
        }

        throw new SchemaException(
            'dto.boolean_required',
            'A boolean cannot be required, because false is indistinguishable from absent: give '
                . $path . ' a constructor default.',
        );
    }

    /**
     * @param class-string $class
     * @return array<string, ReflectionParameter>
     */
    private function parameters(string $class): array
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException('DTO class does not exist or cannot be autoloaded: ' . $class);
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isFinal() || !$reflection->isReadOnly()) {
            throw new InvalidArgumentException('DTO class must be final readonly: ' . $class);
        }

        $constructor = $reflection->getConstructor();
        $publicProperties = array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property): bool => !$property->isStatic(),
        );
        if ($constructor === null) {
            if ($publicProperties !== []) {
                throw new InvalidArgumentException('DTO properties must use constructor promotion: ' . $class);
            }
            return [];
        }
        if (!$constructor->isPublic()) {
            throw new InvalidArgumentException('DTO constructor must be public: ' . $class);
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            if (!$parameter->isPromoted()) {
                throw new InvalidArgumentException(
                    'Every DTO constructor parameter must promote a public property: ' . $class . '::$' . $parameter->getName(),
                );
            }
            $property = $reflection->getProperty($parameter->getName());
            if (!$property->isPublic() || $property->isStatic()) {
                throw new InvalidArgumentException('DTO properties must be public instance properties: ' . $class);
            }
            if ($parameter->getType() === null) {
                throw new InvalidArgumentException('DTO properties must declare a PHP type: ' . $class . '::$' . $parameter->getName());
            }
            $parameters[$parameter->getName()] = $parameter;
        }

        if (count($parameters) !== count($publicProperties)) {
            throw new InvalidArgumentException('Every public DTO property must be constructor-promoted: ' . $class);
        }

        return $parameters;
    }

    /** @param class-string $class */
    private function property(string $class, ReflectionParameter $parameter): ReflectionProperty
    {
        return (new ReflectionClass($class))->getProperty($parameter->getName());
    }

    private function metadata(ReflectionProperty $property): ?ApiField
    {
        $attributes = $property->getAttributes(ApiField::class);
        if (count($attributes) > 1) {
            throw new InvalidArgumentException('Only one ApiField attribute may be used on ' . $property->getName() . '.');
        }

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * @param list<class-string> $stack
     * @param bool $request whether the class is read from a caller or written to one
     */
    private function schema(
        ?ReflectionType $type,
        ?ApiField $metadata,
        array $stack,
        bool $request,
        string $phpDocDescription = '',
    ): Schema {
        if ($type === null) {
            throw new InvalidArgumentException('DTO properties must declare a PHP type.');
        }

        [$name, $nullable] = $this->namedType($type);
        [$schemaType, $enum, $items, $properties, $required] = $this->shape($name, $metadata, $stack, $request);

        $description = $metadata !== null && $metadata->description !== ''
            ? $metadata->description
            : $phpDocDescription;

        return new Schema(
            type: $schemaType,
            nullable: $nullable,
            format: $metadata?->format,
            properties: $properties,
            items: $items,
            enum: $enum,
            description: $description,
            requiredProperties: $required,
            minLength: $metadata?->minLength,
            maxLength: $metadata?->maxLength,
            minimum: $metadata?->minimum,
            maximum: $metadata?->maximum,
        );
    }

    /** @return array{string, bool} */
    private function namedType(ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName(), $type->allowsNull()];
        }
        if (!$type instanceof ReflectionUnionType) {
            throw new InvalidArgumentException('Intersection DTO property types are not supported.');
        }

        $names = [];
        $nullable = false;
        foreach ($type->getTypes() as $member) {
            if (!$member instanceof ReflectionNamedType) {
                throw new InvalidArgumentException('Intersection DTO property types are not supported.');
            }
            if ($member->getName() === 'null') {
                $nullable = true;
            } else {
                $names[] = $member->getName();
            }
        }
        if (count($names) !== 1) {
            throw new InvalidArgumentException('Only nullable unions such as string|null are supported.');
        }

        return [$names[0], $nullable];
    }

    /**
     * The parts of a schema that follow from the PHP type alone.
     *
     * @param list<class-string> $stack
     * @return array{
     *     string,
     *     list<string|int|float|bool|null>,
     *     Schema|null,
     *     array<string, Schema>,
     *     list<string>
     * }
     */
    private function shape(string $name, ?ApiField $metadata, array $stack, bool $request): array
    {
        $builtin = match ($name) {
            'string' => 'string',
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            default => null,
        };
        if ($builtin !== null) {
            if ($builtin !== 'array') {
                return [$builtin, [], null, [], []];
            }
            if ($metadata?->items === null) {
                throw new InvalidArgumentException('Array DTO properties require ApiField(items: ...).');
            }

            return ['array', [], $this->itemSchema($metadata->items, $stack, $request), [], []];
        }

        if (enum_exists($name)) {
            $enum = new ReflectionEnum($name);
            if (!$enum->isBacked()) {
                throw new InvalidArgumentException('Only backed enums are supported in DTOs: ' . $name);
            }
            $backing = $enum->getBackingType()->getName();
            $values = array_values(array_map(
                static fn (ReflectionEnumBackedCase $case): string|int => $case->getBackingValue(),
                $enum->getCases(),
            ));

            return [$backing === 'int' ? 'integer' : 'string', $values, null, [], []];
        }

        if (!class_exists($name)) {
            throw new InvalidArgumentException('Unsupported DTO property type: ' . $name);
        }
        if (in_array($name, $stack, true)) {
            throw new InvalidArgumentException('Circular DTO reference: ' . implode(' -> ', [...$stack, $name]));
        }

        [$properties, $required] = $this->objectProperties($name, [...$stack, $name], $request);

        return ['object', [], null, $properties, $required];
    }

    /**
     * @param list<class-string> $stack
     */
    private function itemSchema(string $name, array $stack, bool $request): Schema
    {
        [$type, $enum, $items, $properties, $required] = $this->shape($name, null, $stack, $request);

        return new Schema(
            type: $type,
            properties: $properties,
            items: $items,
            enum: $enum,
            requiredProperties: $required,
        );
    }

    /**
     * Every promoted property of a response DTO is a required object property, because an instance
     * of it always carries one. A request is read rather than built, so there a property states its
     * own presence the way a top level field does: a constructor default, or `@must`, makes it
     * optional, and what is optional is neither demanded of the caller nor documented as required.
     *
     * @param class-string $class
     * @param list<class-string> $stack
     * @return array{array<string, Schema>, list<string>}
     */
    private function objectProperties(string $class, array $stack, bool $request): array
    {
        $properties = [];
        $required = [];
        foreach ($this->parameters($class) as $name => $parameter) {
            if (preg_match('/^[a-z_][a-z0-9_]*$/', $name) !== 1) {
                throw new InvalidArgumentException(
                    'DTO property names must match ^[a-z_][a-z0-9_]*$: ' . $class . '::$' . $name,
                );
            }
            $property = $this->property($class, $parameter);
            $doc = $this->phpDoc->field($property->getDocComment(), $class . '::$' . $name);
            $schema = $this->schema(
                $parameter->getType(),
                $this->metadata($property),
                $stack,
                $request,
                $doc['description'],
            );

            if (!$request || ($doc['must'] ?? !$parameter->isDefaultValueAvailable())) {
                $required[] = $name;
            } elseif ($parameter->isDefaultValueAvailable()) {
                $schema = $schema->withDefault($parameter->getDefaultValue());
            } else {
                throw new InvalidArgumentException(
                    $class . '::$' . $name . ' uses @must false but has no constructor default.',
                );
            }

            $properties[$name] = $schema;
        }

        ksort($properties, SORT_STRING);
        sort($required, SORT_STRING);

        return [$properties, $required];
    }
}
