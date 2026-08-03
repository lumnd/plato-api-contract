<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Console;

use RuntimeException;

/**
 * A command that has already decided both what went wrong and what to exit with.
 *
 * It exists so the steps `api:generate` and `api:check` share - building the adapter, loading
 * contracts, planning - can report their own failure without every caller re-deciding the exit code.
 *
 * @internal
 */
final class CommandFailure extends RuntimeException
{
    /**
     * @param list<string> $messages
     */
    public function __construct(
        public readonly int $exitCode,
        public readonly array $messages,
    ) {
        parent::__construct($messages[0] ?? 'The command failed.');
    }
}
