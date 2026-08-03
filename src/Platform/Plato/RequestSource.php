<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Ir\Field;
use Lumnd\PlatoApiContract\Ir\Operation;

/**
 * Where one request field is read from at runtime.
 */
interface RequestSource
{
    /**
     * A PHP expression that yields the raw value of the field.
     */
    public function expression(Field $field, Operation $operation): string;

    /**
     * Class imports the generated controller needs for these expressions.
     *
     * @return list<string>
     */
    public function imports(): array;
}
