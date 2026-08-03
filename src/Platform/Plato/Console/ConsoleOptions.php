<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato\Console;

use Lumnd\PlatoApiContract\Console\ProjectConfig;
use plato\console\console;

/** Reads only options explicitly passed to PlatoPHP's console. */
final class ConsoleOptions
{
    private function __construct()
    {
    }

    /** @return array<string, string|bool> */
    public static function given(): array
    {
        $options = [];
        $names = ['config'];
        foreach (ProjectConfig::KEYS as $key) {
            if ($key !== 'strategies') {
                $names[] = $key;
            }
        }

        foreach ($names as $name) {
            /** @var mixed $value */
            $value = console::option($name);
            if ($value !== null) {
                $options[$name] = is_bool($value) ? $value : (string) $value;
            }
        }

        return $options;
    }
}
