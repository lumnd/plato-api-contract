<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use Closure;

/** @internal One declarative output rule in a TemplatePack. */
final readonly class TemplateArtifactDefinition
{
    /**
     * @param Closure(TemplateContext): string $path
     * @param Closure(TemplateContext): array<string, mixed>|null $variables
     */
    public function __construct(
        public TemplateScope $scope,
        public string $template,
        public Closure $path,
        public Ownership $ownership,
        public ?Closure $variables = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function variables(TemplateContext $context): array
    {
        if ($this->variables === null) {
            return ['view' => $context, 'helpers' => $context->helpers];
        }

        $variables = ($this->variables)($context);
        $variables['helpers'] ??= $context->helpers;

        return $variables;
    }
}
