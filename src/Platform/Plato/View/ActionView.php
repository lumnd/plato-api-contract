<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato\View;

use Lumnd\PlatoApiContract\Generation\TemplateApi;
use Lumnd\PlatoApiContract\Generation\TemplateView;

/**
 * One controller action, with every runtime decision already made.
 *
 * Every expression here comes from a platform strategy. A template decides how they are laid out and
 * in which order they appear; it cannot change where a field is read, how it is validated or what is
 * returned.
 */
final readonly class ActionView implements TemplateView
{
    /**
     * @param array<string, string> $inputs field name to the expression reading it
     * @param string $validatorExpression validates `$input` and yields a validator object
     * @param string $failureStatement a complete statement, semicolon included, answering a failed
     *                                 validation: a return, or a throw of the project's own exception
     * @param string $requestExpression builds the request DTO from `$validated`
     * @param string $logicExpression calls the user owned Logic with the request and the context
     * @param string $responseExpression turns `$response` into the framework response
     */
    public function __construct(
        public string $name,
        public string $httpMethod,
        public string $path,
        public string $returnType,
        public array $inputs,
        public string $validatorExpression,
        public string $failureStatement,
        public string $requestExpression,
        public string $logicExpression,
        public string $responseExpression,
    ) {
    }

    public function templateApiVersion(): int
    {
        return TemplateApi::VERSION;
    }
}
