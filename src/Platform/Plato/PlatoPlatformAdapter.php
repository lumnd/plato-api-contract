<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Contract\RouteConvention;
use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Generation\GenerationContext;
use Lumnd\PlatoApiContract\Generation\PlatformAdapter;
use Lumnd\PlatoApiContract\Generation\Ownership;
use Lumnd\PlatoApiContract\Generation\TemplateRenderer;
use Lumnd\PlatoApiContract\Generation\TemplateContext;
use Lumnd\PlatoApiContract\Generation\TemplatePack;
use Lumnd\PlatoApiContract\Generation\TemplatePackBuilder;
use Lumnd\PlatoApiContract\Ir\ContractCollection;

/**
 * The PlatoPHP platform: every `plato\*` generation decision of this package lives in this namespace.
 */
final class PlatoPlatformAdapter implements PlatformAdapter
{
    public const NAME = 'plato';

    private readonly PlatoConfig $config;

    private readonly PlatoControllerGenerator $controllers;

    private readonly PlatoLogicGenerator $logic;

    private readonly TemplatePack $pack;

    public function __construct(
        ?PlatoConfig $config = null,
        ?TemplateRenderer $templates = null,
        ?PlatoControllerGenerator $controllers = null,
        ?PlatoLogicGenerator $logic = null,
        private readonly PlatoRouteConvention $routes = new PlatoRouteConvention(),
        private readonly PlatoCapabilities $capabilities = new PlatoCapabilities(),
    ) {
        $this->config = $config ?? new PlatoConfig();
        $renderer = $templates ?? PlatoTemplates::renderer($this->config->templateDirectory);
        $this->controllers = $controllers ?? new PlatoControllerGenerator();
        $this->logic = $logic ?? new PlatoLogicGenerator();
        $this->pack = (new TemplatePackBuilder(self::NAME, $this->routes, $renderer))
            ->validate(fn (ContractCollection $contracts): array => $this->capabilities->errors($contracts))
            ->eachApi(
                'controller',
                fn (TemplateContext $view): string => $this->config->controllerPath($this->api($view)),
                Ownership::Generated,
                fn (TemplateContext $view): array => [
                    'view' => $this->controllers->view($this->api($view), $this->config),
                ],
            )
            ->eachOperation(
                'logic',
                fn (TemplateContext $view): string => $this->config->logicPath(
                    $this->logic->logicClass($this->api($view), $this->operation($view), $this->config),
                ),
                Ownership::User,
                fn (TemplateContext $view): array => [
                    'view' => $this->logic->view($this->api($view), $this->operation($view), $this->config),
                ],
            )
            ->build();
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function routeConvention(): RouteConvention
    {
        return $this->routes;
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode("\0", [
            self::NAME,
            self::class,
            $this->config->hash(),
            // Namespaces and directories are not the whole configuration: the strategies decide what
            // the controller does, so a project that replaced one has a different configuration
            $this->controllers->fingerprint(),
        ]));
    }

    public function templateFingerprint(): string
    {
        return $this->pack->templateFingerprint();
    }

    public function generate(ContractCollection $contracts, GenerationContext $context): array
    {
        return $this->pack->generate($contracts, $context);
    }

    private function api(TemplateContext $view): \Lumnd\PlatoApiContract\Ir\ApiContract
    {
        if ($view->api === null) {
            throw new GenerationException(['The Plato API template requires an API context.']);
        }

        return $view->api;
    }

    private function operation(TemplateContext $view): \Lumnd\PlatoApiContract\Ir\Operation
    {
        if ($view->operation === null) {
            throw new GenerationException(['The Plato Logic template requires an operation context.']);
        }

        return $view->operation;
    }
}
