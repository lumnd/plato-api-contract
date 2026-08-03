<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * Builds one named platform from project options.
 *
 * The console runner only knows this interface. Framework-specific option parsing stays beside the
 * platform it configures instead of leaking into the shared CLI.
 */
interface PlatformFactory
{
    public function name(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options): PlatformAdapter;
}
