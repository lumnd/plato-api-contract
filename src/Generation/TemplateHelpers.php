<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use InvalidArgumentException;

/** Named helper objects available to every template in a pack. */
final readonly class TemplateHelpers
{
    /** @param array<string, object> $helpers */
    public function __construct(private array $helpers = [])
    {
        foreach ($helpers as $name => $helper) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                throw new InvalidArgumentException('Invalid template helper name: ' . $name);
            }
        }
    }

    public function get(string $name): object
    {
        if (!isset($this->helpers[$name])) {
            throw new InvalidArgumentException('Unknown template helper: ' . $name);
        }

        return $this->helpers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->helpers[$name]);
    }

    /** @return array<string, object> */
    public function all(): array
    {
        return $this->helpers;
    }
}
