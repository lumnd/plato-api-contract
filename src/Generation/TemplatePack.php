<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use Closure;
use Lumnd\PlatoApiContract\Contract\RouteConvention;
use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Ir\ContractCollection;

/** A complete platform adapter declared as artifact templates plus optional PHP helpers. */
final class TemplatePack implements PlatformAdapter
{
    /**
     * @param list<TemplateArtifactDefinition> $artifacts
     * @param list<Closure(ContractCollection): list<string>> $validators
     */
    public function __construct(
        private readonly string $name,
        private readonly RouteConvention $routes,
        private readonly TemplateRenderer $templates,
        private readonly array $artifacts,
        private readonly TemplateHelpers $helpers,
        private readonly array $validators = [],
        private readonly string $definitionFingerprint = '',
    ) {
    }

    public static function define(
        string $name,
        RouteConvention $routes,
        string $templateDirectory,
        ?string $overrideDirectory = null,
    ): TemplatePackBuilder {
        $directories = $overrideDirectory === null || $overrideDirectory === ''
            ? [$templateDirectory]
            : [$overrideDirectory, $templateDirectory];

        return new TemplatePackBuilder(
            $name,
            $routes,
            new PhpTemplateRenderer(new TemplateLocator($directories)),
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function routeConvention(): RouteConvention
    {
        return $this->routes;
    }

    public function fingerprint(): string
    {
        $definitions = array_map(
            static fn (TemplateArtifactDefinition $artifact): string => implode(':', [
                $artifact->scope->value,
                $artifact->template,
                $artifact->ownership->value,
            ]),
            $this->artifacts,
        );
        $helpers = array_map(
            static fn (object $helper): string => $helper::class,
            $this->helpers->all(),
        );

        return hash('sha256', implode("\0", [
            $this->name,
            $this->routes::class,
            $this->routes->name(),
            ...$definitions,
            ...$helpers,
            $this->definitionFingerprint,
        ]));
    }

    public function templateFingerprint(): string
    {
        return $this->templates->fingerprint();
    }

    public function generate(ContractCollection $contracts, GenerationContext $context): array
    {
        $errors = [];
        foreach ($this->validators as $validator) {
            foreach ($validator($contracts) as $error) {
                $errors[] = $error;
            }
        }
        if ($errors !== []) {
            throw new GenerationException($errors);
        }

        $generated = [];
        foreach ($this->artifacts as $artifact) {
            foreach ($this->contexts($artifact->scope, $contracts, $context) as $view) {
                $generated[] = new GeneratedArtifact(
                    ($artifact->path)($view),
                    $this->templates->render($artifact->template, $artifact->variables($view)),
                    $artifact->ownership,
                );
            }
        }

        return $generated;
    }

    public function withDefinitionFingerprint(string $fingerprint): self
    {
        return new self(
            $this->name,
            $this->routes,
            $this->templates,
            $this->artifacts,
            $this->helpers,
            $this->validators,
            hash('sha256', $this->definitionFingerprint . "\0" . $fingerprint),
        );
    }

    /** @return list<TemplateContext> */
    private function contexts(
        TemplateScope $scope,
        ContractCollection $contracts,
        GenerationContext $generation,
    ): array {
        if ($scope === TemplateScope::Collection) {
            return [new TemplateContext($contracts, $generation, $this->helpers)];
        }

        $contexts = [];
        foreach ($contracts->apis as $api) {
            if ($scope === TemplateScope::Api) {
                $contexts[] = new TemplateContext($contracts, $generation, $this->helpers, $api);
                continue;
            }
            foreach ($api->operations as $operation) {
                $contexts[] = new TemplateContext($contracts, $generation, $this->helpers, $api, $operation);
            }
        }

        return $contexts;
    }
}
