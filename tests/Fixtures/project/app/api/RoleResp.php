<?php

declare(strict_types=1);

namespace Fixture\api;

final readonly class RoleResp
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}
