<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Dsl;

final readonly class Endpoint
{
    /**
     * @param class-string|RuleSet $request
     * @param class-string|RuleSet $response
     * @param string $auth what the operation asks of the caller: required, optional or none
     * @param list<string> $tags
     */
    public function __construct(
        public string $method,
        public string $path,
        public string|RuleSet $request,
        public string|RuleSet $response,
        public ?string $handler = null,
        public string $auth = 'required',
        public string $summary = '',
        public int $status = 200,
        public string $description = '',
        public array $tags = [],
        public bool $deprecated = false,
        public ?string $operationId = null,
        public ?string $sourceFile = null,
        public ?int $sourceLine = null,
    ) {
    }
}
