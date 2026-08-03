<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

/**
 * How a controller reaches the user-owned Logic of one operation.
 */
interface LogicResolver
{
    public function logicClass(string $symbol): string;

    /**
     * A PHP expression calling the Logic with the request DTO and the API context.
     */
    public function callExpression(PlatoConfig $config, string $symbol, string $request, string $context): string;
}
