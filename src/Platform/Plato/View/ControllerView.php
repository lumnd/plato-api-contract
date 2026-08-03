<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato\View;

use Lumnd\PlatoApiContract\Generation\TemplateApi;
use Lumnd\PlatoApiContract\Generation\TemplateView;

/**
 * One generated controller class.
 */
final readonly class ControllerView implements TemplateView
{
    /**
     * @param list<string> $imports fully qualified names, already de-duplicated and sorted
     * @param string $actionsCode the exported `$actions` map, ready to embed
     * @param list<ActionView> $actions in contract order
     */
    public function __construct(
        public string $namespace,
        public string $class,
        public string $marker,
        public array $imports,
        public string $actionsCode,
        public array $actions,
    ) {
    }

    public function templateApiVersion(): int
    {
        return TemplateApi::VERSION;
    }
}
