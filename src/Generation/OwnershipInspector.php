<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * Compares what a run would write against what the last run recorded and what is on disk.
 *
 * The rule is one sentence: a generated file may be replaced only when this tool can account for
 * every byte in it, either because it recorded writing them or because it is about to write exactly
 * the same bytes again. Everything else is somebody's edit.
 *
 * A file whose contents already match the plan is current even when no manifest mentions it, so a
 * project that adopts the manifest with its generated output committed and up to date is adopted in
 * silence rather than accused of editing.
 */
final class OwnershipInspector
{
    /**
     * @param list<GeneratedArtifact> $planned
     */
    public function inspect(string $root, Manifest $recorded, array $planned): OwnershipReport
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $states = [];
        $plannedPaths = [];

        foreach ($planned as $artifact) {
            if ($artifact->ownership === Ownership::Tool) {
                // The manifest describes the other files; it never describes or defends itself.
                continue;
            }

            $plannedPaths[$artifact->path] = true;
            $states[] = new ArtifactState(
                $artifact->path,
                $artifact->ownership,
                $this->plannedStatus($root, $recorded, $artifact),
            );
        }

        foreach ($recorded->entries as $entry) {
            if (isset($plannedPaths[$entry->path]) || !$entry->ownership->isTracked()) {
                // A Logic file the application owns stays untracked once it is scaffolded: its
                // endpoint may be gone, but the code in it never belonged to this tool.
                continue;
            }

            $orphan = $this->orphanStatus($root, $entry);
            if ($orphan !== null) {
                $states[] = new ArtifactState($entry->path, $entry->ownership, $orphan);
            }
        }

        return new OwnershipReport($states);
    }

    private function plannedStatus(string $root, Manifest $recorded, GeneratedArtifact $artifact): ArtifactStatus
    {
        $contents = $this->read($root, $artifact->path);
        if ($contents === null) {
            return ArtifactStatus::Create;
        }
        if (!$artifact->ownership->replacesExisting()) {
            return ArtifactStatus::Kept;
        }
        if ($contents === $artifact->contents) {
            return ArtifactStatus::Current;
        }

        $entry = $recorded->entry($artifact->path);
        if ($entry !== null && $entry->sha256 === hash('sha256', $contents)) {
            return ArtifactStatus::Update;
        }

        return ArtifactStatus::Modified;
    }

    private function orphanStatus(string $root, ManifestEntry $entry): ?ArtifactStatus
    {
        $contents = $this->read($root, $entry->path);
        if ($contents === null) {
            return null;
        }

        return $entry->sha256 === hash('sha256', $contents)
            ? ArtifactStatus::Orphaned
            : ArtifactStatus::OrphanedModified;
    }

    private function read(string $root, string $path): ?string
    {
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($target)) {
            return null;
        }

        $contents = file_get_contents($target);

        return $contents === false ? null : $contents;
    }
}
