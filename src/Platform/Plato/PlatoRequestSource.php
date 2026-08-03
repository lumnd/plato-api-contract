<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Ir\Field;
use Lumnd\PlatoApiContract\Ir\Operation;

/**
 * PlatoPHP reads every input source through its own request helpers.
 */
final class PlatoRequestSource implements RequestSource
{
    public function expression(Field $field, Operation $operation): string
    {
        $name = var_export($field->name, true);

        return match ($field->source) {
            'query' => 'req::get(' . $name . ')',
            'header' => 'req::headers(' . $name . ')',
            'cookie' => 'req::cookie(' . $name . ')',
            'json' => 'req::json(' . $name . ')',
            'form' => 'req::' . strtolower($operation->method) . '(' . $name . ')',
            'file' => 'upload::info(' . $name . ')',
            'segment' => 'route::segments()[' . (int) $field->segmentIndex . '] ?? null',
            default => 'null',
        };
    }

    public function imports(): array
    {
        return ['plato\\http\\req', 'plato\\http\\route', 'plato\\http\\upload'];
    }
}
