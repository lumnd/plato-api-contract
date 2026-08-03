<?php

declare(strict_types=1);

namespace Fixture\api;

/**
 * A request whose boolean insists on being sent, which false can never satisfy.
 */
final readonly class BooleanReq
{
    public function __construct(
        public bool $enabled,
    ) {
    }
}
