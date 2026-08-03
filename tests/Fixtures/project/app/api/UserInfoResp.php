<?php

declare(strict_types=1);

namespace Fixture\api;

final readonly class UserInfoResp
{
    public function __construct(
        /** @desc User ID. */
        public int $id,
        /** @desc User name. */
        public string $username,
        /** @desc User email address. */
        public string $email,
    ) {
    }
}
