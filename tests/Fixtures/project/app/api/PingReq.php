<?php

declare(strict_types=1);

namespace Fixture\api;

use Lumnd\PlatoApiContract\Dsl\ApiField;

final readonly class PingReq
{
    public function __construct(
        /** @desc Message to echo back. */
        #[ApiField(minLength: 1, maxLength: 100)]
        public string $message,
    ) {
    }
}
