<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Ir;

use InvalidArgumentException;

final readonly class Response
{
    /** @param class-string|null $dataClass null when Logic returns an array */
    public function __construct(
        public int $status,
        public Schema $data,
        public ?string $dataClass,
        public string $description = 'Successful response',
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Invalid HTTP response status: ' . $status);
        }
    }
}
