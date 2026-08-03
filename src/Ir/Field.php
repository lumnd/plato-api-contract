<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Ir;

use InvalidArgumentException;

final readonly class Field
{
    public const SOURCES = ['query', 'header', 'cookie', 'json', 'form', 'file', 'segment'];
    public const TYPES = ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null'];

    /**
     * @param list<string|int|float|bool|null> $enum
     */
    public function __construct(
        public string $name,
        public string $source,
        public string $type,
        public bool $required = false,
        public bool $nullable = false,
        public mixed $default = null,
        public ?string $format = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public array $enum = [],
        public string $description = '',
        public ?int $segmentIndex = null,
        public bool $hasDefault = false,
        public ?Schema $schema = null,
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public ?string $pattern = null,
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException('Invalid field name: ' . $name);
        }

        if (!in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException('Unknown field source: ' . $source);
        }

        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown field type: ' . $type);
        }

        if ($required && ($hasDefault || $default !== null)) {
            throw new InvalidArgumentException('A required field cannot define a default: ' . $name);
        }

        if ($minLength !== null && $maxLength !== null && $minLength > $maxLength) {
            throw new InvalidArgumentException('minLength cannot exceed maxLength for field: ' . $name);
        }

        if ($source === 'segment' && $segmentIndex === null) {
            throw new InvalidArgumentException('A segment field requires segmentIndex: ' . $name);
        }

        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new InvalidArgumentException('minimum cannot exceed maximum for field: ' . $name);
        }
    }
}
