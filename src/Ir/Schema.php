<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Ir;

use InvalidArgumentException;

final readonly class Schema
{
    /**
     * @param array<string, Schema> $properties
     * @param list<string> $requiredProperties
     * @param list<string|int|float|bool|null> $enum
     */
    public function __construct(
        public string $type,
        public bool $nullable = false,
        public ?string $format = null,
        public array $properties = [],
        public ?self $items = null,
        public array $enum = [],
        public string $description = '',
        public array $requiredProperties = [],
        public mixed $default = null,
        public bool $hasDefault = false,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public ?string $pattern = null,
    ) {
        if (!in_array($type, Field::TYPES, true)) {
            throw new InvalidArgumentException('Unknown schema type: ' . $type);
        }

        if ($type === 'array' && $items === null) {
            throw new InvalidArgumentException('An array schema requires an item schema.');
        }

        foreach ($requiredProperties as $property) {
            if (!isset($properties[$property])) {
                throw new InvalidArgumentException('Required object property is not defined: ' . $property);
            }
        }

        if ($minLength !== null && $maxLength !== null && $minLength > $maxLength) {
            throw new InvalidArgumentException('Schema minLength cannot exceed maxLength.');
        }

        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new InvalidArgumentException('Schema minimum cannot exceed maximum.');
        }
    }

    /**
     * The same schema with a declared default, which a DTO carries as a constructor default.
     */
    public function withDefault(mixed $default): self
    {
        return new self(
            type: $this->type,
            nullable: $this->nullable,
            format: $this->format,
            properties: $this->properties,
            items: $this->items,
            enum: $this->enum,
            description: $this->description,
            requiredProperties: $this->requiredProperties,
            default: $default,
            hasDefault: true,
            minLength: $this->minLength,
            maxLength: $this->maxLength,
            minimum: $this->minimum,
            maximum: $this->maximum,
            pattern: $this->pattern,
        );
    }
}
