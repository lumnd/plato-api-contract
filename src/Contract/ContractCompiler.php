<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use InvalidArgumentException;
use Lumnd\PlatoApiContract\Dsl\Endpoint;
use Lumnd\PlatoApiContract\Dsl\Envelope;
use Lumnd\PlatoApiContract\Dsl\RuleSet;
use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Ir\AuthMode;
use Lumnd\PlatoApiContract\Ir\Operation;
use Lumnd\PlatoApiContract\Ir\PathTemplate;
use Lumnd\PlatoApiContract\Ir\Response;
use Lumnd\PlatoApiContract\Ir\ResponseEnvelope;

/**
 * Compiles one contract file into IR.
 *
 * A contract declares endpoints as PHP objects and points at the application's own readonly DTO
 * classes; everything the IR needs is either declared on the endpoint or reflected out of those DTOs.
 * Every failure becomes a {@see ContractIssue} with a stable code, a file and a `$.services.user[0]`
 * style path, so a contract is never half compiled into IR.
 */
final class ContractCompiler
{
    private const FILE_KEYS = ['syntax', 'description', 'envelope', 'services'];

    public function __construct(
        private readonly DtoSchemaReflector $reflector = new DtoSchemaReflector(),
        private readonly RuleSetCompiler $rules = new RuleSetCompiler(),
        private readonly PhpDocMetadata $phpDoc = new PhpDocMetadata(),
        private readonly RouteConvention $routes = new StandardRouteConvention(),
        private readonly PathParser $paths = new PathParser(),
    ) {
    }

    /**
     * @param list<ContractIssue> $issues
     * @return list<ApiContract>
     */
    public function compile(ContractFile $file, array &$issues): array
    {
        $data = $file->data;
        foreach (array_keys($data) as $key) {
            if (!in_array($key, self::FILE_KEYS, true)) {
                $issues[] = new ContractIssue(
                    'structure.unknown_key',
                    $file->path,
                    '$.' . (string) $key,
                    'Unknown contract key: ' . (string) $key,
                );
            }
        }

        if (($data['syntax'] ?? null) !== 'v1') {
            $issues[] = new ContractIssue(
                'contract.syntax_unsupported',
                $file->path,
                '$.syntax',
                "Every contract file must declare syntax => 'v1'.",
            );
        }

        foreach ($this->phpDoc->validateContractFile($file->path) as $docIssue) {
            $issues[] = new ContractIssue(
                $docIssue['code'],
                $file->path,
                '$.phpdoc.line_' . $docIssue['line'],
                $docIssue['message'],
            );
        }

        $envelope = $this->envelope($data['envelope'] ?? new Envelope(), $file, $issues);
        $services = $data['services'] ?? null;
        if (!is_array($services) || $services === [] || array_is_list($services)) {
            $issues[] = new ContractIssue(
                'contract.services',
                $file->path,
                '$.services',
                'services must be a non-empty map keyed by controller name.',
            );
            return [];
        }

        $apis = [];
        foreach ($services as $service => $definitions) {
            $servicePath = '$.services.' . (string) $service;
            if (!is_string($service) || preg_match('/^[a-z_][a-z0-9_]*$/', $service) !== 1) {
                $issues[] = new ContractIssue(
                    'contract.name',
                    $file->path,
                    $servicePath,
                    'Service name must match ^[a-z_][a-z0-9_]*$.',
                );
                continue;
            }

            $operations = $this->operations($service, $definitions, $file, $servicePath, $issues);
            if ($operations === []) {
                continue;
            }

            try {
                $apis[] = new ApiContract(
                    name: $service,
                    description: $this->description($data, $service, $file, $issues),
                    operations: $operations,
                    envelope: $envelope,
                );
            } catch (InvalidArgumentException $exception) {
                $issues[] = new ContractIssue('contract.invalid', $file->path, $servicePath, $exception->getMessage());
            }
        }

        return $apis;
    }

    /**
     * @param list<ContractIssue> $issues
     * @return list<Operation>
     */
    private function operations(
        string $service,
        mixed $definitions,
        ContractFile $file,
        string $servicePath,
        array &$issues,
    ): array {
        $endpoints = $definitions instanceof Endpoint ? [$definitions] : $definitions;
        if (!is_array($endpoints) || $endpoints === []) {
            $issues[] = new ContractIssue(
                'contract.endpoints',
                $file->path,
                $servicePath,
                'A service must contain one endpoint or a non-empty endpoint list.',
            );
            return [];
        }

        $operations = [];
        $actions = [];
        foreach (array_values($endpoints) as $index => $endpoint) {
            $path = $servicePath . '[' . $index . ']';
            if (!$endpoint instanceof Endpoint) {
                $issues[] = new ContractIssue(
                    'operation.type',
                    $file->path,
                    $path,
                    'Service entries must be Endpoint objects created by endpoint(), get() or post().',
                );
                continue;
            }

            $route = $this->route($service, $endpoint, $file, $path, $issues);
            if ($route === null) {
                continue;
            }
            [$action, $template] = $route;
            if (isset($actions[$action])) {
                $issues[] = new ContractIssue(
                    'operation.name_duplicate',
                    $file->path,
                    $path,
                    'Action is duplicated in service ' . $service . ': ' . $action,
                );
                continue;
            }

            $operation = $this->operation($service, $action, $template, $endpoint, $file, $path, $issues);
            if ($operation === null) {
                continue;
            }

            $actions[$action] = true;
            $operations[] = $operation;
        }

        return $operations;
    }

    /**
     * @param list<ContractIssue> $issues
     */
    private function operation(
        string $service,
        string $action,
        PathTemplate $template,
        Endpoint $endpoint,
        ContractFile $file,
        string $path,
        array &$issues,
    ): ?Operation {
        try {
            $requestFields = $endpoint->request instanceof RuleSet
                ? $this->rules->request($endpoint->request, $endpoint->method, $template->parameters)
                : $this->reflector->request($endpoint->request, $endpoint->method, $template->parameters);
            $responseSchema = $endpoint->response instanceof RuleSet
                ? $this->rules->response($endpoint->response)
                : $this->reflector->response($endpoint->response);
        } catch (SchemaException $exception) {
            $issues[] = new ContractIssue($exception->issueCode, $file->path, $path, $exception->getMessage());
            return null;
        } catch (InvalidArgumentException $exception) {
            $issues[] = new ContractIssue('dto.invalid', $file->path, $path, $exception->getMessage());
            return null;
        }

        $operationId = $this->operationId($endpoint, $file, $path, $issues);

        $auth = AuthMode::tryFrom($endpoint->auth);
        if ($auth === null) {
            $issues[] = new ContractIssue(
                'endpoint.auth',
                $file->path,
                $path . '.auth',
                'auth must be one of required, optional or none; got "' . $endpoint->auth . '".',
            );
            return null;
        }

        $doc = $this->phpDoc->endpoint($endpoint);

        try {
            return new Operation(
                action: $action,
                method: $endpoint->method,
                summary: $endpoint->summary !== ''
                    ? $endpoint->summary
                    : ($doc['title'] ?? $service . '.' . $action),
                auth: $auth,
                requestFields: $requestFields,
                responses: [new Response(
                    $endpoint->status,
                    $responseSchema,
                    is_string($endpoint->response) ? $endpoint->response : null,
                )],
                path: $template,
                requestClass: is_string($endpoint->request) ? $endpoint->request : null,
                operationId: $operationId,
                description: $endpoint->description !== ''
                    ? $endpoint->description
                    : ($doc['description'] ?? ''),
                tags: $endpoint->tags,
                deprecated: $endpoint->deprecated,
            );
        } catch (InvalidArgumentException $exception) {
            $issues[] = new ContractIssue('operation.invalid', $file->path, $path, $exception->getMessage());
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<ContractIssue> $issues
     */
    private function description(array $data, string $service, ContractFile $file, array &$issues): string
    {
        $description = $data['description'] ?? $service . ' API';
        if (!is_string($description)) {
            $issues[] = new ContractIssue(
                'contract.description_type',
                $file->path,
                '$.description',
                'description must be a string.',
            );
            return $service . ' API';
        }

        return $description;
    }

    /**
     * @param list<ContractIssue> $issues
     */
    private function operationId(Endpoint $endpoint, ContractFile $file, string $path, array &$issues): ?string
    {
        $declared = $endpoint->operationId;
        if ($declared === null) {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9._-]+$/', $declared) !== 1) {
            $issues[] = new ContractIssue(
                'operation.id',
                $file->path,
                $path . '.operationId',
                'operationId may contain only letters, numbers, _, . and -.',
            );
            return null;
        }

        return $declared;
    }

    /**
     * @param list<ContractIssue> $issues
     */
    private function envelope(mixed $value, ContractFile $file, array &$issues): ResponseEnvelope
    {
        if (!$value instanceof Envelope) {
            $issues[] = new ContractIssue(
                'contract.envelope',
                $file->path,
                '$.envelope',
                'envelope must be created by envelope(); omit it for the code/msg/data default.',
            );
            $value = new Envelope();
        }

        try {
            return new ResponseEnvelope(
                $value->statusField,
                $value->successValue,
                $value->messageField,
                $value->successMessage,
                $value->dataField,
            );
        } catch (InvalidArgumentException $exception) {
            $issues[] = new ContractIssue('contract.envelope', $file->path, '$.envelope', $exception->getMessage());
            return new ResponseEnvelope('code', 0, 'msg', 'successful', 'data');
        }
    }

    /**
     * @param list<ContractIssue> $issues
     * @return array{string, PathTemplate}|null
     */
    private function route(
        string $service,
        Endpoint $endpoint,
        ContractFile $file,
        string $path,
        array &$issues,
    ): ?array {
        $template = $this->paths->parse($endpoint->path, $file->path, $path . '.path', $issues);
        if ($template === null) {
            return null;
        }

        if (!$this->paths->checkConvention($this->routes, $service, $template, $file->path, $path . '.path', $issues)) {
            return null;
        }

        $handler = $endpoint->handler === null ? null : $this->snake($endpoint->handler);
        $action = $this->routes->action($service, $template, $handler);
        if ($action === null) {
            return null;
        }

        if ($handler !== null && $handler !== $action) {
            $issues[] = new ContractIssue(
                'operation.handler',
                $file->path,
                $path . '.handler',
                'Handler must resolve to the action ' . $action . '.',
            );
            return null;
        }

        return [$action, $template];
    }

    private function snake(string $name): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name;
        return strtolower(str_replace('-', '_', $snake));
    }
}
