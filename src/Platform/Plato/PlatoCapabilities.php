<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\Ir\Operation;

/**
 * What this platform can serve, checked before a single file is produced.
 *
 * An IR feature the adapter cannot express must fail here with a locatable diagnostic, never as an
 * internal error from the middle of code generation.
 */
final class PlatoCapabilities
{
    public function __construct(
        private readonly PlatoRouteConvention $routes = new PlatoRouteConvention(),
    ) {
    }

    /**
     * @return list<string> empty when every contract can be generated
     */
    public function errors(ContractCollection $contracts): array
    {
        $errors = [];
        foreach ($contracts->apis as $api) {
            foreach ($api->operations as $operation) {
                foreach ($this->operationErrors($api, $operation) as $error) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function operationErrors(ApiContract $api, Operation $operation): array
    {
        $where = $api->name . '.' . $operation->action . ': ';
        $errors = [];

        foreach ($this->routes->violations($api->name, $operation->path) as $violation) {
            $errors[] = $where . $violation['message'];
        }

        return $errors;
    }
}
