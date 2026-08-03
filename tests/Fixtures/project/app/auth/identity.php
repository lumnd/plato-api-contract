<?php

declare(strict_types=1);

namespace Fixture\auth;

/**
 * The fixture application's own identity, standing in for a real authenticated user.
 */
final class identity
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
