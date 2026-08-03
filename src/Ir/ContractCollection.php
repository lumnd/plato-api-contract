<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Ir;

use InvalidArgumentException;

final readonly class ContractCollection
{
    /**
     * @param list<ApiContract> $apis
     */
    public function __construct(
        public array $apis,
    ) {
        if ($apis === []) {
            throw new InvalidArgumentException('A contract collection requires at least one API.');
        }
    }

    public function api(string $name): ?ApiContract
    {
        foreach ($this->apis as $api) {
            if ($api->name === $name) {
                return $api;
            }
        }

        return null;
    }
}
