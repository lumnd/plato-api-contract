<?php

declare(strict_types=1);

namespace Fixture\api;

final readonly class PingResp
{
    public function __construct(
        /** @desc The echoed message. */
        public string $message,
    ) {
    }
}
