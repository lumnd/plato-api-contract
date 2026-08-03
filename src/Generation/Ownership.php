<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * Who owns a file after it has been written.
 *
 * Ownership is the whole basis of the manifest: it decides what may be replaced, what is recorded so
 * a later run can tell "the contract changed" from "a human changed this", and what is removed when
 * its contract disappears.
 */
enum Ownership: string
{
    /** The generator's file: replaced on every change, tracked by hash, removed when unreachable. */
    case Generated = 'generated';

    /** Scaffolded once into the application's own code, then never read, replaced or removed. */
    case User = 'user';

    /** The manifest itself: always rewritten, never recorded and never protected. */
    case Tool = 'tool';

    /** Whether a run may write over a file that already exists. */
    public function replacesExisting(): bool
    {
        return $this !== self::User;
    }

    /** Whether the manifest records this file's hash, which is what protects it from a silent overwrite. */
    public function isTracked(): bool
    {
        return $this === self::Generated;
    }
}
