<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * One file of the project, as a generation run finds it.
 */
final readonly class ArtifactState
{
    public function __construct(
        public string $path,
        public Ownership $ownership,
        public ArtifactStatus $status,
    ) {
    }
}
