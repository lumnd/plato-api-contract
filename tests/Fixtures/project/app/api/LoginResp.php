<?php

declare(strict_types=1);

namespace Fixture\api;

final readonly class LoginResp
{
    public function __construct(
        public string $token,
    ) {
    }
}
