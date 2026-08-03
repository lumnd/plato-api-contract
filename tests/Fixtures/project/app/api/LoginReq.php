<?php

declare(strict_types=1);

namespace Fixture\api;

final readonly class LoginReq
{
    public function __construct(
        public string $username,
        public string $password,
        public ?bool $remember = null,
    ) {
    }
}
