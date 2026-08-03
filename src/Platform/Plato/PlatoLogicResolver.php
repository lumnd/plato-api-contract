<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

/**
 * PlatoAdmin style: one final class per operation with a static handle().
 *
 * ADR 0001 keeps this a static handler rather than a resolver, to match existing application code.
 */
final class PlatoLogicResolver implements LogicResolver
{
    public function logicClass(string $symbol): string
    {
        return $symbol;
    }

    public function callExpression(PlatoConfig $config, string $symbol, string $request, string $context): string
    {
        return $config->logicFqn($this->logicClass($symbol)) . "::handle(\n"
            . '            ' . $request . ",\n"
            . '            ' . $context . ",\n"
            . '        )';
    }
}
