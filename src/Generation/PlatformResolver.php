<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use InvalidArgumentException;

/** Resolves a bundled platform name, an adapter object, or a PHP template-pack definition file. */
final class PlatformResolver
{
    /** @var array<string, PlatformFactory> */
    private array $factories = [];

    /** @param list<PlatformFactory> $factories */
    public function __construct(
        array $factories = [],
        private readonly PlatformFileLoader $files = new PlatformFileLoader(),
    ) {
        foreach ($factories as $factory) {
            $this->register($factory);
        }
    }

    public function register(PlatformFactory $factory): void
    {
        $this->factories[$factory->name()] = $factory;
    }

    /** @param array<string, mixed> $options */
    public function resolve(mixed $platform, array $options = []): PlatformAdapter
    {
        if ($platform instanceof PlatformAdapter) {
            return $platform;
        }

        if (!is_string($platform) || $platform === '') {
            throw new InvalidArgumentException(
                'Platform must be an adapter name, a platform definition file, or a PlatformAdapter object.',
            );
        }

        if (is_file($platform)) {
            return $this->files->load($platform, $options);
        }

        if ($this->looksLikePath($platform)) {
            throw new InvalidArgumentException('Platform definition file does not exist: ' . $platform);
        }

        if (!isset($this->factories[$platform])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown platform adapter "%s". Available: %s.',
                $platform,
                implode(', ', $this->names()),
            ));
        }

        return $this->factories[$platform]->create($options);
    }

    /** @return list<string> */
    public function names(): array
    {
        $names = array_keys($this->factories);
        sort($names, SORT_STRING);

        return $names;
    }

    private function looksLikePath(string $platform): bool
    {
        return str_ends_with($platform, '.php')
            || str_contains($platform, '/')
            || str_contains($platform, '\\');
    }
}
