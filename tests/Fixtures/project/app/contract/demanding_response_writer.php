<?php

declare(strict_types=1);

namespace Fixture\contract;

use Lumnd\PlatoApiContract\Ir\ResponseEnvelope;
use Lumnd\PlatoApiContract\Platform\Plato\ResponseWriter;

/**
 * A strategy the generator has no way to construct: it is refused before generation, not halfway
 * through it.
 */
final class demanding_response_writer implements ResponseWriter
{
    public function __construct(private readonly string $prefix)
    {
    }

    public function expression(ResponseEnvelope $envelope, string $data, int $status): string
    {
        return $this->prefix . $data;
    }

    public function returnType(): string
    {
        return 'reply';
    }

    public function imports(): array
    {
        return [];
    }
}
