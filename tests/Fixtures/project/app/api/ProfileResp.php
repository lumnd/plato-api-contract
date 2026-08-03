<?php

declare(strict_types=1);

namespace Fixture\api;

final readonly class ProfileResp
{
    public function __construct(
        public string $nickname,
    ) {
    }
}
