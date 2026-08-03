<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Runtime;

final readonly class ApiContext
{
    /** @param list<string> $segments */
    public function __construct(
        public ?object $auth,
        public array $segments,
    ) {
    }
}
