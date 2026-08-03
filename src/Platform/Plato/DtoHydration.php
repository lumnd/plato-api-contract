<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Ir\Operation;

/**
 * How validated input becomes the request DTO, and how the response DTO becomes an array.
 */
interface DtoHydration
{
    /**
     * A PHP expression building the request Logic receives out of the read input variable.
     *
     * The input is what the controller read for the fields the contract declared, after validation
     * accepted it. It is not the validator's own output, which answers a different question and
     * carries only the fields that had something to check.
     */
    public function request(Operation $operation, string $input): string;

    /**
     * A PHP expression turning the Logic response into an array.
     */
    public function response(Operation $operation, string $response): string;

    /** @return list<string> */
    public function imports(Operation $operation): array;
}
