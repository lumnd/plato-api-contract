<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Ir;

use InvalidArgumentException;

final readonly class Operation
{
    /**
     * @param list<Field> $requestFields
     * @param list<Response> $responses
     * @param list<string> $tags
     * @param class-string|null $requestClass null when Logic receives the validated array
     */
    public function __construct(
        public string $action,
        public string $method,
        public string $summary,
        public AuthMode $auth,
        public array $requestFields,
        public array $responses,
        public PathTemplate $path,
        public ?string $requestClass,
        public ?string $operationId = null,
        public string $description = '',
        public array $tags = [],
        public bool $deprecated = false,
    ) {
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $action) !== 1) {
            throw new InvalidArgumentException('Invalid action name: ' . $action);
        }

        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException('Unsupported HTTP method: ' . $method);
        }

        if ($responses === []) {
            throw new InvalidArgumentException('An operation requires at least one response.');
        }

        $names = [];
        foreach ($requestFields as $field) {
            $key = $field->source . ':' . $field->name;
            if (isset($names[$key])) {
                throw new InvalidArgumentException('Duplicate request field source and name: ' . $key);
            }
            $names[$key] = true;
        }

        $segments = [];
        foreach ($requestFields as $field) {
            if ($field->source === 'segment') {
                $segments[(int) $field->segmentIndex] = $field->name;
            }
        }
        ksort($segments, SORT_NUMERIC);
        if (array_values($segments) !== $path->parameters) {
            throw new InvalidArgumentException(sprintf(
                'Path parameters [%s] do not match the segment request fields [%s] of action %s.',
                implode(', ', $path->parameters),
                implode(', ', array_values($segments)),
                $action,
            ));
        }
    }

    public function id(string $controller): string
    {
        return $this->operationId ?? $controller . '.' . $this->action;
    }

    public function primaryResponse(): Response
    {
        return $this->responses[0];
    }
}
