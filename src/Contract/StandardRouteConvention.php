<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use Lumnd\PlatoApiContract\Ir\PathTemplate;

/**
 * Framework-neutral HTTP paths for template packs that generate an explicit route table.
 *
 * Any normalized path is accepted. A contract may name its handler explicitly; otherwise the last
 * literal path segment becomes the action. Frameworks with stronger constraints can supply their
 * own RouteConvention without changing the template-pack API.
 */
final class StandardRouteConvention implements RouteConvention
{
    public function name(): string
    {
        return 'standard';
    }

    public function violations(string $controller, PathTemplate $path): array
    {
        return [];
    }

    public function action(string $controller, PathTemplate $path, ?string $handler = null): ?string
    {
        if ($handler !== null) {
            return $handler;
        }

        $literals = $path->literals();
        $action = $literals[array_key_last($literals)] ?? $controller;
        $action = preg_replace('/[^A-Za-z0-9_]+/', '_', $action) ?? $action;
        $action = strtolower(trim($action, '_'));

        return preg_match('/^[a-z_][a-z0-9_]*$/', $action) === 1 ? $action : null;
    }

    public function defaultPath(string $controller, string $action, array $parameters): PathTemplate
    {
        return PathTemplate::of([$controller, $action], $parameters);
    }
}
