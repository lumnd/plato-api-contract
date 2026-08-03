<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Contract\RouteConvention;
use Lumnd\PlatoApiContract\Ir\PathTemplate;

/**
 * PlatoPHP routes `/{controller}/{action}` plus an ordered tail of positional segments.
 *
 * This is the only place that knows about that constraint. The generic compile layer accepts any
 * syntactically valid path and lets the platform decide whether it can serve it.
 */
final class PlatoRouteConvention implements RouteConvention
{
    public function name(): string
    {
        return 'plato';
    }

    public function violations(string $controller, PathTemplate $path): array
    {
        $segments = explode('/', substr($path->value, 1));
        $violations = [];

        if (count($segments) < 2) {
            return [[
                'code' => 'route.unsupported_shape',
                'message' => 'PlatoPHP routes /{controller}/{action}[/{parameter}...]; got ' . $path->value . '.',
            ]];
        }

        [$service, $action] = [$segments[0], $segments[1]];
        foreach ([$service, $action] as $literal) {
            if (preg_match('/^[a-z_][a-z0-9_]*$/', $literal) !== 1) {
                return [[
                    'code' => 'route.unsupported_shape',
                    'message' => 'PlatoPHP controller and action segments must match ^[a-z_][a-z0-9_]*$: '
                        . $path->value . '.',
                ]];
            }
        }

        if ($service !== $controller) {
            $violations[] = [
                'code' => 'route.controller_mismatch',
                'message' => 'The first path segment must be the controller name ' . $controller
                    . ': ' . $path->value . '.',
            ];
        }

        foreach (array_slice($segments, 2) as $index => $segment) {
            if ($path->parameterIndex($this->parameterName($segment) ?? '') === null) {
                $violations[] = [
                    'code' => 'route.unsupported_shape',
                    'message' => 'PlatoPHP can only route positional parameters after the action; '
                        . 'segment ' . ($index + 3) . ' of ' . $path->value . ' is a literal.',
                ];
                break;
            }
        }

        return $violations;
    }

    public function action(string $controller, PathTemplate $path, ?string $handler = null): ?string
    {
        if ($this->violations($controller, $path) !== []) {
            return null;
        }

        return explode('/', substr($path->value, 1))[1];
    }

    public function defaultPath(string $controller, string $action, array $parameters): PathTemplate
    {
        return PathTemplate::of([$controller, $action], $parameters);
    }

    private function parameterName(string $segment): ?string
    {
        if (strlen($segment) > 1 && str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
            return substr($segment, 1, -1);
        }

        return null;
    }
}
