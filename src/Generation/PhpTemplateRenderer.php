<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use Lumnd\PlatoApiContract\Exception\TemplateException;
use Throwable;

/**
 * Native PHP templates, rendered in an isolated scope with an explicit variable array.
 *
 * ADR 0004 chose plain PHP over a template dependency: `php -l` already covers the output, and PHP is
 * the language every consumer of this package reads. A template receives its view model as `$view`
 * and this renderer as `$templates`, so one template can compose another (a controller renders its
 * actions) without reaching into the generator.
 */
final class PhpTemplateRenderer implements TemplateRenderer
{
    public function __construct(private readonly TemplateLocator $templates)
    {
    }

    public function render(string $template, array $variables): string
    {
        $file = $this->templates->locate($template);
        $variables['templates'] ??= $this;

        $level = ob_get_level();
        try {
            return self::isolated($file, $variables);
        } catch (TemplateException $exception) {
            // A nested render already names the template that actually failed.
            throw $exception;
        } catch (Throwable $exception) {
            throw TemplateException::failed($template, $file, $exception);
        } finally {
            // A template that dies mid-output must not leave a buffer behind for the next artifact.
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    public function fingerprint(): string
    {
        return $this->templates->fingerprint();
    }

    public function locator(): TemplateLocator
    {
        return $this->templates;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private static function isolated(string $file, array $variables): string
    {
        // The names are prefixed so that a view variable can never collide with them.
        return (static function () use ($file, $variables): string {
            $__template_file = $file;
            $__template_variables = $variables;
            unset($file, $variables);
            extract($__template_variables, EXTR_SKIP);
            ob_start();
            include $__template_file;
            $rendered = ob_get_clean();

            return $rendered === false ? '' : $rendered;
        })();
    }
}
