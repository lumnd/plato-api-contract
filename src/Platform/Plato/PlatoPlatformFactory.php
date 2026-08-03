<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Generation\PlatformAdapter;
use Lumnd\PlatoApiContract\Generation\PlatformFactory;

/** Builds the bundled Plato platform without teaching the shared console runner Plato options. */
final class PlatoPlatformFactory implements PlatformFactory
{
    public function name(): string
    {
        return PlatoPlatformAdapter::NAME;
    }

    public function create(array $options): PlatformAdapter
    {
        $templatesDirectory = (string) ($options['templates'] ?? '');
        $exception = (string) ($options['exception'] ?? '');
        $config = new PlatoConfig(
            controllerNamespace: (string) ($options['controller-namespace'] ?? 'control'),
            logicNamespace: (string) ($options['logic-namespace'] ?? 'logic'),
            controllerDirectory: (string) ($options['controller-dir'] ?? 'app/control'),
            logicDirectory: (string) ($options['logic-dir'] ?? 'app/logic'),
            templateDirectory: $templatesDirectory === '' ? null : $templatesDirectory,
            exception: $exception === '' ? null : $exception,
        );

        PlatoRefusal::verify($config);

        /** @var array<string, string> $strategies */
        $strategies = is_array($options['strategies'] ?? null) ? $options['strategies'] : [];
        if ($strategies === []) {
            return new PlatoPlatformAdapter($config);
        }

        $templates = PlatoTemplates::renderer($config->templateDirectory);

        return new PlatoPlatformAdapter(
            $config,
            $templates,
            PlatoStrategies::generator($strategies),
        );
    }
}
