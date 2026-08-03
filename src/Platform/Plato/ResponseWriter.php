<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Ir\ResponseEnvelope;

/**
 * How a Logic result becomes an HTTP response.
 */
interface ResponseWriter
{
    /**
     * A PHP expression returning the framework response.
     *
     * @param string $data expression producing the response payload array
     */
    public function expression(ResponseEnvelope $envelope, string $data, int $status): string;

    public function returnType(): string;

    /** @return list<string> */
    public function imports(): array;
}
