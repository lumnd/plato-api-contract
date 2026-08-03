<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use InvalidArgumentException;

/**
 * Adapters are selected by composition, never by a conditional inside the pipeline.
 */
final class PlatformRegistry
{
    /** @var array<string, PlatformAdapter> */
    private array $adapters = [];

    /**
     * @param list<PlatformAdapter> $adapters
     */
    public function __construct(array $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $this->register($adapter);
        }
    }

    public function register(PlatformAdapter $adapter): void
    {
        $this->adapters[$adapter->name()] = $adapter;
    }

    public function get(string $name): PlatformAdapter
    {
        if (!isset($this->adapters[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown platform adapter "%s". Available: %s.',
                $name,
                implode(', ', $this->names()),
            ));
        }

        return $this->adapters[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->adapters[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        $names = array_keys($this->adapters);
        sort($names, SORT_STRING);

        return $names;
    }
}
