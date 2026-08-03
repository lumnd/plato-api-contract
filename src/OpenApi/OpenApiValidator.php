<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\OpenApi;

final class OpenApiValidator
{
    /**
     * Fast, dependency-free checks run before writing. CI additionally runs Redocly's OAS 3.1 validator.
     *
     * @param array<string, mixed> $document
     * @return list<string>
     */
    public function validate(array $document): array
    {
        $errors = [];
        if (($document['openapi'] ?? null) !== '3.1.0') {
            $errors[] = 'openapi must be 3.1.0';
        }
        if (!is_array($document['info'] ?? null) || !is_string($document['info']['title'] ?? null)) {
            $errors[] = 'info.title is required';
        }
        if (!is_array($document['paths'] ?? null) || $document['paths'] === []) {
            $errors[] = 'paths must contain at least one operation';
            return $errors;
        }

        $operationIds = [];
        foreach ($document['paths'] as $path => $item) {
            if (!is_string($path) || !str_starts_with($path, '/') || !is_array($item)) {
                $errors[] = 'Every path must start with / and contain a Path Item';
                continue;
            }
            foreach ($item as $method => $operation) {
                if (!in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true) || !is_array($operation)) {
                    $errors[] = $path . ' contains an invalid operation';
                    continue;
                }
                $id = $operation['operationId'] ?? null;
                if (!is_string($id) || $id === '') {
                    $errors[] = $path . ':' . $method . ' requires operationId';
                } elseif (isset($operationIds[$id])) {
                    $errors[] = 'Duplicate operationId: ' . $id;
                } else {
                    $operationIds[$id] = true;
                }
                if (!is_array($operation['responses'] ?? null) || $operation['responses'] === []) {
                    $errors[] = $path . ':' . $method . ' requires responses';
                }
            }
        }

        return $errors;
    }
}
