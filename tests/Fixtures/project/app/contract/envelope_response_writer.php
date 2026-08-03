<?php

declare(strict_types=1);

namespace Fixture\contract;

use Lumnd\PlatoApiContract\Ir\ResponseEnvelope;
use Lumnd\PlatoApiContract\Platform\Plato\ResponseWriter;

/**
 * A project whose envelope is its own: one helper, one HTTP status, business codes in the body.
 *
 * Stands in for the real thing in tests -- an application that answers every request with 200 and
 * says what happened in the envelope, rather than in the status line.
 */
final class envelope_response_writer implements ResponseWriter
{
    public function expression(ResponseEnvelope $envelope, string $data, int $status): string
    {
        return 'resp::response(0, ' . $data . ', \'success\')';
    }

    public function returnType(): string
    {
        return 'reply';
    }

    public function imports(): array
    {
        return ['plato\\http\\reply', 'plato\\http\\resp'];
    }
}
