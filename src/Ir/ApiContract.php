<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Ir;

use InvalidArgumentException;

final readonly class ApiContract
{
    /**
     * @param list<Operation> $operations
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $operations,
        public ResponseEnvelope $envelope,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException('Invalid controller name: ' . $name);
        }

        if ($operations === []) {
            throw new InvalidArgumentException('An API contract requires at least one operation.');
        }

        $actions = [];
        foreach ($operations as $operation) {
            if (isset($actions[$operation->action])) {
                throw new InvalidArgumentException('Duplicate action: ' . $operation->action);
            }
            $actions[$operation->action] = true;
        }
    }
}
