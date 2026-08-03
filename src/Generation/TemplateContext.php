<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\Ir\Operation;

/**
 * Stable entry point handed to a framework template.
 *
 * A collection template receives only contracts; API and operation templates additionally receive
 * the item at their declared scope. The normalized contract IR is intentionally readable here: a
 * trusted template pack is the platform adapter, not a layout-only project override.
 */
final readonly class TemplateContext implements TemplateView
{
    public function __construct(
        public ContractCollection $contracts,
        public GenerationContext $generation,
        public TemplateHelpers $helpers,
        public ?ApiContract $api = null,
        public ?Operation $operation = null,
    ) {
    }

    public function templateApiVersion(): int
    {
        return TemplateApi::VERSION;
    }
}
