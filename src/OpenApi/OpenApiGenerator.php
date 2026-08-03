<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\OpenApi;

use JsonException;
use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Ir\AuthMode;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\Ir\Field;
use Lumnd\PlatoApiContract\Ir\Operation;
use Lumnd\PlatoApiContract\Ir\Schema;

final class OpenApiGenerator
{
    /**
     * One document for the whole application, which is what a project publishes.
     *
     * @return array<string, mixed>
     * @throws GenerationException when two contracts claim the same path and method
     */
    public function collectionDocument(ContractCollection $contracts, DocumentOptions $options): array
    {
        return $this->build(
            $contracts->apis,
            $options,
            $options->title ?? 'API',
            $options->version ?? '1.0.0',
        );
    }

    /**
     * @throws JsonException
     * @throws GenerationException
     */
    public function collectionJson(ContractCollection $contracts, DocumentOptions $options): string
    {
        return $this->encode($this->collectionDocument($contracts, $options));
    }

    /**
     * @param list<ApiContract> $apis
     * @return array<string, mixed>
     * @throws GenerationException
     */
    private function build(array $apis, DocumentOptions $options, string $title, string $version): array
    {
        $paths = [];
        $owners = [];
        $errors = [];
        $needsBearer = false;

        foreach ($apis as $api) {
            foreach ($api->operations as $operation) {
                $path = $this->path($operation, $options);
                $method = strtolower($operation->method);
                $owner = $api->name . '.' . $operation->action;
                if (isset($owners[$path][$method])) {
                    $errors[] = sprintf(
                        '%s %s is claimed by both %s and %s.',
                        strtoupper($method),
                        $path,
                        $owners[$path][$method],
                        $owner,
                    );
                    continue;
                }

                $owners[$path][$method] = $owner;
                $paths[$path][$method] = $this->operation($api, $operation);
                $needsBearer = $needsBearer || $operation->auth !== AuthMode::None;
            }
        }

        if ($errors !== []) {
            throw new GenerationException($errors);
        }

        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $title,
                'version' => $version,
            ],
            'paths' => $paths,
        ];

        if ($needsBearer) {
            $document['components'] = [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ];
        }

        return $this->sort($document);
    }

    /**
     * @param array<string, mixed> $document
     * @throws JsonException
     */
    private function encode(array $document): string
    {
        return json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . "\n";
    }

    /** @return array<string, mixed> */
    private function operation(ApiContract $api, Operation $operation): array
    {
        $parameters = [];
        $bodyFields = [];

        foreach ($operation->requestFields as $field) {
            if (in_array($field->source, ['query', 'header', 'cookie', 'segment'], true)) {
                $parameters[] = [
                    'name' => $field->name,
                    'in' => $field->source === 'segment' ? 'path' : $field->source,
                    'required' => $field->source === 'segment' || $field->required,
                    'description' => $field->description,
                    'schema' => $this->fieldSchema($field),
                ];
            } else {
                $bodyFields[] = $field;
            }
        }

        usort($parameters, static fn (array $left, array $right): int => [$left['in'], $left['name']] <=> [$right['in'], $right['name']]);

        $result = [
            'operationId' => $operation->id($api->name),
            'summary' => $operation->summary,
            'description' => $operation->description,
            'tags' => $operation->tags === [] ? [$api->name] : $operation->tags,
            'deprecated' => $operation->deprecated,
            'responses' => $this->responses($api, $operation),
        ];

        if ($parameters !== []) {
            $result['parameters'] = $parameters;
        }

        if ($bodyFields !== []) {
            $result['requestBody'] = $this->requestBody($bodyFields);
        }

        // An optional identity is two admitted schemes, the empty one included: a caller may sign
        // in and be recognised, or not and still be served.
        $result['security'] = match ($operation->auth) {
            AuthMode::None => [],
            AuthMode::Optional => [[], ['bearerAuth' => []]],
            AuthMode::Required => [['bearerAuth' => []]],
        };

        return $result;
    }

    /** @return array<int|string, mixed> */
    private function responses(ApiContract $api, Operation $operation): array
    {
        $responses = [];
        foreach ($operation->responses as $response) {
            $responses[(string) $response->status] = [
                'description' => $response->description,
                'content' => [
                    'application/json' => [
                        'schema' => $this->responseSchema($api, $response->data),
                    ],
                ],
            ];
        }

        // PlatoPHP rejects a route that requires an identity before dispatch. An optional one is
        // served either way, so it has no 401 to document.
        if ($operation->auth->requiresIdentity()) {
            $responses['401'] ??= $this->refusal($api, 401, 'Unauthorized', 'The request carries no authenticated identity.');
        }

        ksort($responses, SORT_STRING);
        return $responses;
    }

    /**
     * A refusal produced by the framework before controller dispatch.
     *
     * @return array<string, mixed>
     */
    private function refusal(ApiContract $api, int $status, string $message, string $description): array
    {
        $properties = [
            $api->envelope->statusField => ['type' => 'integer', 'example' => $status],
            $api->envelope->messageField => ['type' => 'string', 'example' => $message],
            $api->envelope->dataField => ['type' => 'null'],
        ];
        ksort($properties, SORT_STRING);
        $required = array_keys($properties);
        sort($required, SORT_STRING);

        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $required,
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function responseSchema(ApiContract $api, Schema $data): array
    {
        $properties = [
            $api->envelope->statusField => [
                'type' => 'integer',
                'example' => $api->envelope->successValue,
            ],
            $api->envelope->messageField => [
                'type' => 'string',
                'example' => $api->envelope->successMessage,
            ],
            $api->envelope->dataField => $this->schema($data),
        ];
        ksort($properties, SORT_STRING);
        $required = array_keys($properties);
        sort($required, SORT_STRING);

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * @param list<Field> $fields
     * @return array<string, mixed>
     */
    private function requestBody(array $fields): array
    {
        $properties = [];
        $required = [];
        $sources = [];
        foreach ($fields as $field) {
            $properties[$field->name] = $this->fieldSchema($field);
            $sources[$field->source] = true;
            if ($field->required) {
                $required[] = $field->name;
            }
        }
        sort($required, SORT_STRING);
        ksort($properties, SORT_STRING);

        $contentType = isset($sources['file'])
            ? 'multipart/form-data'
            : (isset($sources['form']) ? 'application/x-www-form-urlencoded' : 'application/json');
        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return [
            'required' => $required !== [],
            'content' => [$contentType => ['schema' => $schema]],
        ];
    }

    /** @return array<string, mixed> */
    private function fieldSchema(Field $field): array
    {
        $schema = $field->schema !== null
            ? $this->schema($field->schema)
            : ['type' => $field->nullable ? [$field->type, 'null'] : $field->type];
        if ($field->format !== null) {
            $schema['format'] = $field->format;
        }
        if ($field->description !== '') {
            $schema['description'] = $field->description;
        }
        if ($field->default !== null) {
            $schema['default'] = $field->default;
        }
        if ($field->enum !== []) {
            $schema['enum'] = $field->enum;
        }
        if ($field->minLength !== null) {
            $schema['minLength'] = $field->minLength;
        }
        if ($field->maxLength !== null) {
            $schema['maxLength'] = $field->maxLength;
        }
        if ($field->minimum !== null) {
            $schema['minimum'] = $field->minimum;
        }
        if ($field->maximum !== null) {
            $schema['maximum'] = $field->maximum;
        }
        if ($field->pattern !== null) {
            $schema['pattern'] = $field->pattern;
        }
        if ($field->source === 'file') {
            $schema['type'] = 'string';
            $schema['format'] = 'binary';
        }

        return $schema;
    }

    /** @return array<string, mixed> */
    private function schema(Schema $schema): array
    {
        $result = ['type' => $schema->nullable ? [$schema->type, 'null'] : $schema->type];
        if ($schema->format !== null) {
            $result['format'] = $schema->format;
        }
        if ($schema->description !== '') {
            $result['description'] = $schema->description;
        }
        if ($schema->enum !== []) {
            $result['enum'] = $schema->enum;
        }
        if ($schema->hasDefault) {
            $result['default'] = $schema->default;
        }
        if ($schema->minLength !== null) {
            $result['minLength'] = $schema->minLength;
        }
        if ($schema->maxLength !== null) {
            $result['maxLength'] = $schema->maxLength;
        }
        if ($schema->minimum !== null) {
            $result['minimum'] = $schema->minimum;
        }
        if ($schema->maximum !== null) {
            $result['maximum'] = $schema->maximum;
        }
        if ($schema->pattern !== null) {
            $result['pattern'] = $schema->pattern;
        }
        if ($schema->type === 'array' && $schema->items !== null) {
            $result['items'] = $this->schema($schema->items);
        }
        if ($schema->type === 'object') {
            $properties = [];
            foreach ($schema->properties as $name => $property) {
                $properties[$name] = $this->schema($property);
            }
            ksort($properties, SORT_STRING);
            $result['properties'] = $properties;
            if ($schema->requiredProperties !== []) {
                $result['required'] = $schema->requiredProperties;
            }
        }

        return $result;
    }

    /**
     * The document path is the canonical IR path plus the document-level base path and suffix.
     * It is never rebuilt from controller and action names, so runtime and documentation cannot fork.
     */
    private function path(Operation $operation, DocumentOptions $options): string
    {
        $base = '/' . trim($options->basePath, '/');
        $base = $base === '/' ? '' : $base;

        return $base . $operation->path->value . $options->pathSuffix;
    }

    private function sort(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sort($item);
        }

        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
