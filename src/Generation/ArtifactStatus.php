<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * What a generation run would do to one file, decided from three facts: what the manifest recorded,
 * what is on disk, and what this run would write.
 */
enum ArtifactStatus: string
{
    /** Nothing is there yet. */
    case Create = 'create';

    /** The file already holds exactly what this run would write. */
    case Current = 'current';

    /** Ours, unchanged since we wrote it, and now out of date. */
    case Update = 'update';

    /** Bytes this tool cannot account for: it neither wrote them nor is about to. */
    case Modified = 'modified';

    /** The application owns it and it exists; generation never looks inside. */
    case Kept = 'kept';

    /** We wrote it, its contract is gone, and it is still exactly as we left it. */
    case Orphaned = 'orphaned';

    /** Its contract is gone and it was edited, so removing it would destroy someone's work. */
    case OrphanedModified = 'orphaned-modified';

    /** Whether generation would change this file. */
    public function isPending(): bool
    {
        return $this === self::Create || $this === self::Update || $this === self::Orphaned;
    }

    /** Whether the file holds bytes this tool did not write. */
    public function isUnaccounted(): bool
    {
        return $this === self::Modified || $this === self::OrphanedModified;
    }
}
