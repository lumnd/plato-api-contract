<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * A read-only value object handed to one template.
 *
 * Layout-only overrides receive platform view models with finished decisions. A complete
 * TemplatePack receives {@see TemplateContext}, whose normalized contracts are deliberately public:
 * the pack is the adapter and therefore owns framework behaviour as well as layout.
 */
interface TemplateView
{
    /**
     * The template API version this view speaks, see {@see TemplateApi::VERSION}.
     */
    public function templateApiVersion(): int;
}
