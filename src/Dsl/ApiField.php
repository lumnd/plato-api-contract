<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Dsl;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ApiField
{
    /** @param list<string|int|float|bool|null> $enum */
    public function __construct(
        public ?string $source = null,
        public ?string $format = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public array $enum = [],
        public string $description = '',
        public ?string $items = null,
    ) {
    }
}
