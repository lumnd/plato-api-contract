<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Exception\TemplateException;
use Lumnd\PlatoApiContract\Generation\PhpTemplateRenderer;
use Lumnd\PlatoApiContract\Generation\TemplateLocator;

/**
 * The templates shipped with the PlatoPHP adapter, and how a project overrides one of them.
 *
 * The search order is "project directory first, built-in directory last", so a project that only
 * wants a different controller layout drops a single `controller.php` next to its contracts and
 * keeps receiving every other file from this package.
 */
final class PlatoTemplates
{
    /** The templates this adapter renders. */
    public const NAMES = ['controller', 'action', 'logic'];

    private function __construct()
    {
    }

    public static function directory(): string
    {
        return dirname(__DIR__, 3) . '/templates/plato';
    }

    /**
     * @throws TemplateException when the override directory does not exist
     */
    public static function locator(?string $override = null): TemplateLocator
    {
        return new TemplateLocator($override === null || $override === ''
            ? [self::directory()]
            : [$override, self::directory()]);
    }

    /**
     * @throws TemplateException when the override directory does not exist
     */
    public static function renderer(?string $override = null): PhpTemplateRenderer
    {
        return new PhpTemplateRenderer(self::locator($override));
    }
}
