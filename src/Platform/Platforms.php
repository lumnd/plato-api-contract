<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform;

use Lumnd\PlatoApiContract\Generation\PlatformResolver;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoPlatformFactory;

/**
 * The adapters shipped with this package.
 *
 * The registry itself stays framework-neutral; this is the one place that knows which adapters exist
 * by default, so a host can build its own registry with third party adapters instead.
 */
final class Platforms
{
    public static function resolver(): PlatformResolver
    {
        return new PlatformResolver([new PlatoPlatformFactory()]);
    }
}
