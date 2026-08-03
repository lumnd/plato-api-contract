<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * The verified plan of a generation run, before or after it is written.
 */
final readonly class GenerationResult
{
    /**
     * @param list<GeneratedArtifact> $artifacts
     * @param list<string> $written relative paths actually written; empty for a planned run
     * @param list<string> $removed relative paths of files this tool wrote and no longer generates
     * @param OwnershipReport|null $report how the project looked before the run, or null when it was
     *                                     only planned
     */
    public function __construct(
        public string $adapter,
        public array $artifacts,
        public GenerationFingerprint $fingerprint,
        public array $written = [],
        public array $removed = [],
        public ?OwnershipReport $report = null,
    ) {
    }

    /** @return list<string> */
    public function paths(): array
    {
        return array_map(static fn (GeneratedArtifact $artifact): string => $artifact->path, $this->artifacts);
    }

    /**
     * @param list<string> $written
     * @param list<string> $removed
     */
    public function withChanges(array $written, array $removed, OwnershipReport $report): self
    {
        return new self($this->adapter, $this->artifacts, $this->fingerprint, $written, $removed, $report);
    }
}
