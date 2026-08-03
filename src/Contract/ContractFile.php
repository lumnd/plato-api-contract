<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

final readonly class ContractFile
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $path,
        public array $data,
    ) {
    }
}
