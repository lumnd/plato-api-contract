<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Ir;

use Lumnd\PlatoApiContract\Exception\PathTemplateException;

/**
 * A framework-neutral, normalized request path.
 *
 * Input may use the brace spelling `/users/{id}` or the colon spelling `/users/:id`. The normalized
 * value always uses `{name}`, so OpenAPI and every platform adapter read the same single field
 * instead of rebuilding a path from controller and action names.
 */
final readonly class PathTemplate
{
    /**
     * @param list<string> $parameters parameter names in path order
     */
    private function __construct(
        public string $value,
        public array $parameters,
    ) {
    }

    /**
     * @throws PathTemplateException
     */
    public static function parse(string $path): self
    {
        if ($path === '' || $path[0] !== '/') {
            throw new PathTemplateException(
                PathTemplateException::SYNTAX,
                'Path must start with "/": ' . $path,
            );
        }

        $segments = explode('/', substr($path, 1));
        if ($segments === ['']) {
            throw new PathTemplateException(
                PathTemplateException::SYNTAX,
                'Path must contain at least one segment: ' . $path,
            );
        }

        $normalized = [];
        $parameters = [];
        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new PathTemplateException(
                    PathTemplateException::SYNTAX,
                    'Path must not contain an empty segment: ' . $path,
                );
            }

            $parameter = self::parameterName($segment);
            if ($parameter === null) {
                if (preg_match('/^[A-Za-z0-9_.~-]+$/', $segment) !== 1) {
                    throw new PathTemplateException(
                        PathTemplateException::SYNTAX,
                        'Path segment contains unsupported characters: ' . $segment,
                    );
                }
                $normalized[] = $segment;
                continue;
            }

            if (preg_match('/^[a-z_][a-z0-9_]*$/', $parameter) !== 1) {
                throw new PathTemplateException(
                    PathTemplateException::PARAMETER_NAME,
                    'Path parameter name must match ^[a-z_][a-z0-9_]*$: ' . $parameter,
                );
            }
            if (in_array($parameter, $parameters, true)) {
                throw new PathTemplateException(
                    PathTemplateException::PARAMETER_DUPLICATE,
                    'Path parameter names must be unique: ' . $parameter,
                );
            }

            $parameters[] = $parameter;
            $normalized[] = '{' . $parameter . '}';
        }

        return new self('/' . implode('/', $normalized), $parameters);
    }

    /**
     * Build a path from already validated literal segments and trailing parameters.
     *
     * @param list<string> $literals
     * @param list<string> $parameters
     * @throws PathTemplateException
     */
    public static function of(array $literals, array $parameters = []): self
    {
        $path = '';
        foreach ($literals as $literal) {
            $path .= '/' . $literal;
        }
        foreach ($parameters as $parameter) {
            $path .= '/{' . $parameter . '}';
        }

        return self::parse($path);
    }

    /** @return list<string> */
    public function literals(): array
    {
        $literals = [];
        foreach (explode('/', substr($this->value, 1)) as $segment) {
            if (self::parameterName($segment) === null) {
                $literals[] = $segment;
            }
        }

        return $literals;
    }

    public function hasParameter(string $name): bool
    {
        return in_array($name, $this->parameters, true);
    }

    public function parameterIndex(string $name): ?int
    {
        $index = array_search($name, $this->parameters, true);

        return $index === false ? null : $index;
    }

    private static function parameterName(string $segment): ?string
    {
        if (str_starts_with($segment, ':')) {
            return substr($segment, 1);
        }
        if (strlen($segment) > 1 && str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
            return substr($segment, 1, -1);
        }

        return null;
    }
}
