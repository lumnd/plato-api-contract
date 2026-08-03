<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Dsl;

/**
 * @param class-string|RuleSet $request
 * @param class-string|RuleSet $response
 * @param list<string> $tags
 */
function endpoint(
    string $method,
    string $path,
    string|RuleSet $request,
    string|RuleSet $response,
    ?string $handler = null,
    string $auth = 'required',
    string $summary = '',
    int $status = 200,
    string $description = '',
    array $tags = [],
    bool $deprecated = false,
    ?string $operationId = null,
): Endpoint {
    return make_endpoint(
        $method,
        $path,
        $request,
        $response,
        $handler,
        $auth,
        $summary,
        $status,
        $description,
        $tags,
        $deprecated,
        $operationId,
        caller_location(),
    );
}

/**
 * @param class-string|RuleSet $request
 * @param class-string|RuleSet $response
 * @param list<string> $tags
 */
function get(
    string $path,
    string|RuleSet $request,
    string|RuleSet $response,
    ?string $handler = null,
    string $auth = 'required',
    string $summary = '',
    int $status = 200,
    string $description = '',
    array $tags = [],
    bool $deprecated = false,
    ?string $operationId = null,
): Endpoint {
    return make_endpoint(
        'GET',
        $path,
        $request,
        $response,
        $handler,
        $auth,
        $summary,
        $status,
        $description,
        $tags,
        $deprecated,
        $operationId,
        caller_location(),
    );
}

/**
 * @param class-string|RuleSet $request
 * @param class-string|RuleSet $response
 * @param list<string> $tags
 */
function post(
    string $path,
    string|RuleSet $request,
    string|RuleSet $response,
    ?string $handler = null,
    string $auth = 'required',
    string $summary = '',
    int $status = 200,
    string $description = '',
    array $tags = [],
    bool $deprecated = false,
    ?string $operationId = null,
): Endpoint {
    return make_endpoint(
        'POST',
        $path,
        $request,
        $response,
        $handler,
        $auth,
        $summary,
        $status,
        $description,
        $tags,
        $deprecated,
        $operationId,
        caller_location(),
    );
}

/**
 * @param class-string|RuleSet $request
 * @param class-string|RuleSet $response
 * @param list<string> $tags
 */
function put(
    string $path,
    string|RuleSet $request,
    string|RuleSet $response,
    ?string $handler = null,
    string $auth = 'required',
    string $summary = '',
    int $status = 200,
    string $description = '',
    array $tags = [],
    bool $deprecated = false,
    ?string $operationId = null,
): Endpoint {
    return make_endpoint('PUT', $path, $request, $response, $handler, $auth, $summary, $status, $description, $tags, $deprecated, $operationId, caller_location());
}

/**
 * @param class-string|RuleSet $request
 * @param class-string|RuleSet $response
 * @param list<string> $tags
 */
function patch(
    string $path,
    string|RuleSet $request,
    string|RuleSet $response,
    ?string $handler = null,
    string $auth = 'required',
    string $summary = '',
    int $status = 200,
    string $description = '',
    array $tags = [],
    bool $deprecated = false,
    ?string $operationId = null,
): Endpoint {
    return make_endpoint('PATCH', $path, $request, $response, $handler, $auth, $summary, $status, $description, $tags, $deprecated, $operationId, caller_location());
}

/**
 * @param class-string|RuleSet $request
 * @param class-string|RuleSet $response
 * @param list<string> $tags
 */
function delete(
    string $path,
    string|RuleSet $request,
    string|RuleSet $response,
    ?string $handler = null,
    string $auth = 'required',
    string $summary = '',
    int $status = 200,
    string $description = '',
    array $tags = [],
    bool $deprecated = false,
    ?string $operationId = null,
): Endpoint {
    return make_endpoint('DELETE', $path, $request, $response, $handler, $auth, $summary, $status, $description, $tags, $deprecated, $operationId, caller_location());
}

/**
 * @param class-string|RuleSet $request
 * @param class-string|RuleSet $response
 * @param list<string> $tags
 * @param array{file?: string, line?: int} $location
 */
function make_endpoint(
    string $method,
    string $path,
    string|RuleSet $request,
    string|RuleSet $response,
    ?string $handler,
    string $auth,
    string $summary,
    int $status,
    string $description,
    array $tags,
    bool $deprecated,
    ?string $operationId,
    array $location,
): Endpoint {
    return new Endpoint(
        $method,
        $path,
        $request,
        $response,
        $handler,
        $auth,
        $summary,
        $status,
        $description,
        $tags,
        $deprecated,
        $operationId,
        $location['file'] ?? null,
        $location['line'] ?? null,
    );
}

/** @return array{file?: string, line?: int} */
function caller_location(): array
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    return $trace[1] ?? [];
}

/**
 * A body described by field paths and their rules, the way a Laravel FormRequest describes one.
 *
 * @param array<string, list<string>|string> $fields
 */
function rules(array $fields): RuleSet
{
    return new RuleSet($fields);
}

function envelope(
    string $statusField = 'code',
    int $successValue = 0,
    string $messageField = 'msg',
    string $successMessage = 'successful',
    string $dataField = 'data',
): Envelope {
    return new Envelope($statusField, $successValue, $messageField, $successMessage, $dataField);
}
