<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use InvalidArgumentException;
use Throwable;

/** Loads a trusted local PHP file that returns a platform adapter or a TemplatePack builder. */
final class PlatformFileLoader
{
    /** @param array<string, mixed> $options */
    public function load(string $file, array $options = []): PlatformAdapter
    {
        $resolved = realpath($file);
        if ($resolved === false || !is_file($resolved)) {
            throw new InvalidArgumentException('Platform definition file does not exist: ' . $file);
        }

        try {
            $platform = (static function (string $__platformFile, array $__platformOptions): mixed {
                $options = $__platformOptions;

                return require $__platformFile;
            })($resolved, $options);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                'Unable to load platform definition ' . $resolved . ': ' . $exception->getMessage(),
                previous: $exception,
            );
        }

        if ($platform instanceof TemplatePackBuilder) {
            $platform = $platform->build();
        }
        if (!$platform instanceof PlatformAdapter) {
            throw new InvalidArgumentException(
                $resolved . ' must return a PlatformAdapter or TemplatePackBuilder.',
            );
        }

        return $platform instanceof TemplatePack
            ? $platform->withDefinitionFingerprint($this->hash($resolved))
            : $platform;
    }

    private function hash(string $file): string
    {
        $hash = hash_file('sha256', $file);

        return $hash === false ? '' : $hash;
    }
}
