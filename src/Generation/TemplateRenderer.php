<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use Lumnd\PlatoApiContract\Exception\TemplateException;

/**
 * Turns one template plus one view model into file contents.
 *
 * A renderer never decides where a file lands: artifact paths stay adapter configuration, so a
 * template cannot write outside the generation root even by accident.
 */
interface TemplateRenderer
{
    /**
     * @param array<string, mixed> $variables the template's local variables, normally `['view' => …]`
     * @throws TemplateException when the template is missing or fails while rendering
     */
    public function render(string $template, array $variables): string;

    /**
     * A hash of every template that can be reached, so the generation fingerprint changes when a
     * project edits an override.
     */
    public function fingerprint(): string;
}
