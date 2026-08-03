<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Ir\Operation;
use Lumnd\PlatoApiContract\Ir\Schema;
use Lumnd\PlatoApiContract\Platform\Plato\View\LogicView;

/**
 * The first user-owned Logic skeleton for every operation.
 */
final class PlatoLogicGenerator
{
    public function __construct(
        private readonly LogicResolver $logic = new PlatoLogicResolver(),
    ) {
    }

    public function logicClass(ApiContract $api, Operation $operation, PlatoConfig $config): string
    {
        return $this->logic->logicClass($config->symbol($api, $operation));
    }

    public function view(ApiContract $api, Operation $operation, PlatoConfig $config): LogicView
    {
        return $this->logicView($config->symbol($api, $operation), $operation, $config);
    }

    private function logicView(string $symbol, Operation $operation, PlatoConfig $config): LogicView
    {
        $response = $operation->primaryResponse();

        return new LogicView(
            namespace: $config->logicNamespace,
            class: $this->logic->logicClass($symbol),
            imports: [
                'LogicException',
                'Lumnd\\PlatoApiContract\\Runtime\\ApiContext',
            ],
            requestType: $operation->requestClass === null ? 'array' : $this->fqn($operation->requestClass),
            responseType: $response->dataClass === null ? 'array' : $this->fqn($response->dataClass),
            contextType: 'ApiContext',
            requestShape: $operation->requestClass === null ? $this->requestShape($operation) : '',
            responseShape: $response->dataClass === null ? $this->shape($response->data, false) : '',
        );
    }

    /**
     * The request as a PHPStan array shape, so a Logic body is checked against the contract.
     *
     * Every declared field is there: the controller projects each one, defaulted or null, rather
     * than handing over whatever the validator happened to keep.
     */
    private function requestShape(Operation $operation): string
    {
        $entries = [];
        foreach ($operation->requestFields as $field) {
            $schema = $field->schema ?? new Schema($field->type, nullable: $field->nullable);
            $entries[] = $field->name . ': ' . $this->type($schema, $field->required, true);
        }

        return $entries === [] ? 'array{}' : 'array{' . implode(', ', $entries) . '}';
    }

    /**
     * @param bool $request whether the shape describes what the controller hands over, where every
     *                      declared key is there, rather than what Logic has to return
     */
    private function shape(Schema $schema, bool $request): string
    {
        if ($schema->type !== 'object') {
            return $this->type($schema, true, $request);
        }

        $entries = [];
        foreach ($schema->properties as $name => $property) {
            $required = in_array($name, $schema->requiredProperties, true);
            $optional = !$request && !$required;
            $entries[] = $name . ($optional ? '?' : '') . ': ' . $this->type($property, $required, $request);
        }

        return $entries === [] ? 'array{}' : 'array{' . implode(', ', $entries) . '}';
    }

    private function type(Schema $schema, bool $required, bool $request): string
    {
        $type = match ($schema->type) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'list<' . $this->type($schema->items ?? new Schema('string'), true, $request) . '>',
            'object' => $this->shape($schema, $request),
            default => 'string',
        };

        // A request key is always there, so what an absent one becomes - null, or the declared
        // default - is part of its type. A response key that may be absent is marked `?` instead.
        $null = $schema->nullable || ($request && !$required && !$schema->hasDefault);

        return $null ? $type . '|null' : $type;
    }

    /** A leading-backslash class name, ready to embed in generated code. */
    private function fqn(string $class): string
    {
        return '\\' . ltrim($class, '\\');
    }
}
