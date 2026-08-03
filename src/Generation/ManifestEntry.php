<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * One file a previous run produced.
 */
final readonly class ManifestEntry
{
    /**
     * @param string $path relative to the generation root
     * @param string|null $sha256 the contents that were written, or null for a file this tool does
     *                            not track: an application owns its Logic and may edit it freely
     */
    public function __construct(
        public string $path,
        public Ownership $ownership,
        public ?string $sha256,
    ) {
    }
}
