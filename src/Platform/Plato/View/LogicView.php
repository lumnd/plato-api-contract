<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato\View;

use Lumnd\PlatoApiContract\Generation\TemplateApi;
use Lumnd\PlatoApiContract\Generation\TemplateView;

/**
 * The first Logic skeleton of one operation.
 *
 * This is the only generated file a project owns afterwards: it is written once and never
 * overwritten, so its template decides what a developer starts from.
 */
final readonly class LogicView implements TemplateView
{
    /**
     * @param list<string> $imports fully qualified names, in the order they should be written
     * @param string $requestShape a PHPStan array shape naming every key the request carries, or an
     *                             empty string when the request is a DTO that already types itself
     * @param string $responseShape the same for the response Logic has to return
     */
    public function __construct(
        public string $namespace,
        public string $class,
        public array $imports,
        public string $requestType,
        public string $responseType,
        public string $contextType,
        public string $requestShape = '',
        public string $responseShape = '',
    ) {
    }

    public function templateApiVersion(): int
    {
        return TemplateApi::VERSION;
    }
}
