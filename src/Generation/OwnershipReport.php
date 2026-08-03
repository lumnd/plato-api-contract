<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * The project as a generation run finds it, one state per file.
 *
 * Both `api:generate` and `api:check` read this: one decides what to write, the other decides what
 * to report, and neither invents its own rules about which files it may touch.
 */
final readonly class OwnershipReport
{
    /**
     * @param list<ArtifactState> $states
     */
    public function __construct(
        public array $states,
    ) {
    }

    /** @return list<ArtifactState> */
    public function withStatus(ArtifactStatus ...$statuses): array
    {
        return array_values(array_filter(
            $this->states,
            static fn (ArtifactState $state): bool => in_array($state->status, $statuses, true),
        ));
    }

    /**
     * Files holding bytes this tool did not write. Generation refuses to touch them.
     *
     * @return list<ArtifactState>
     */
    public function unaccounted(): array
    {
        return array_values(array_filter(
            $this->states,
            static fn (ArtifactState $state): bool => $state->status->isUnaccounted(),
        ));
    }

    /**
     * Files a run would create, rewrite or remove.
     *
     * @return list<ArtifactState>
     */
    public function pending(): array
    {
        return array_values(array_filter(
            $this->states,
            static fn (ArtifactState $state): bool => $state->status->isPending(),
        ));
    }

    /** @return list<string> */
    public function removable(): array
    {
        return array_map(
            static fn (ArtifactState $state): string => $state->path,
            $this->withStatus(ArtifactStatus::Orphaned),
        );
    }

    /** Whether the project already holds exactly what the contracts describe. */
    public function isClean(): bool
    {
        return $this->pending() === [] && $this->unaccounted() === [];
    }

    public function status(string $path): ?ArtifactStatus
    {
        foreach ($this->states as $state) {
            if ($state->path === $path) {
                return $state->status;
            }
        }

        return null;
    }
}
