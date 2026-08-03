<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use Lumnd\PlatoApiContract\Ir\PathTemplate;

/**
 * The routing rules of one platform.
 *
 * Path syntax is framework-neutral and lives in {@see PathTemplate}. Everything a specific framework
 * additionally requires - which shapes it can route at all, how a path maps onto a controller action
 * and which path it uses when a contract does not declare one - belongs to an implementation of this
 * interface, not to the generic compile layer.
 */
interface RouteConvention
{
    public function name(): string;

    /**
     * @return list<array{code: string, message: string}> empty when the path is routable
     */
    public function violations(string $controller, PathTemplate $path): array;

    /**
     * The action name this path dispatches to, or null when the path is not routable.
     */
    public function action(string $controller, PathTemplate $path, ?string $handler = null): ?string;

    /**
     * The path used when a contract declares an action without an explicit path.
     *
     * @param list<string> $parameters
     */
    public function defaultPath(string $controller, string $action, array $parameters): PathTemplate;
}
