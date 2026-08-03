<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use RuntimeException;

/**
 * The only place that touches the project's files.
 *
 * It writes as little as it can: a file already holding the exact bytes of the plan is left alone,
 * so a repeated run changes no modification time and `git status` shows what a regeneration really
 * did. Whether an edited file may be replaced is not decided here - by the time the writer runs, the
 * pipeline has already checked ownership.
 */
final class ArtifactWriter
{
    /**
     * @param list<GeneratedArtifact> $artifacts
     * @return list<string> the relative paths that changed
     */
    public function write(string $root, array $artifacts): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $written = [];

        foreach ($artifacts as $artifact) {
            $path = $this->absolute($root, $artifact->path);
            if (is_file($path)) {
                if (!$artifact->ownership->replacesExisting()) {
                    continue;
                }
                if (file_get_contents($path) === $artifact->contents) {
                    continue;
                }
            }

            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create output directory: ' . $directory);
            }

            $temporary = tempnam($directory, '.api-contract-');
            if ($temporary === false || file_put_contents($temporary, $artifact->contents) === false) {
                throw new RuntimeException('Unable to write generated artifact: ' . $path);
            }
            if (!rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('Unable to replace generated artifact: ' . $path);
            }
            $written[] = $artifact->path;
        }

        return $written;
    }

    /**
     * Remove files this tool wrote and no longer generates.
     *
     * Only paths the manifest still accounts for byte for byte reach this method. A deleted endpoint
     * whose controller stayed behind is a route the application still serves and the contracts no
     * longer describe - the same documentation/runtime fork this package exists to prevent.
     *
     * @param list<string> $paths
     * @return list<string> the relative paths that were removed
     */
    public function remove(string $root, array $paths): array
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $removed = [];
        $targets = [];

        foreach ($paths as $path) {
            $target = $this->absolute($root, $path);
            if (!is_file($target)) {
                continue;
            }
            if (!is_writable(dirname($target))) {
                throw new RuntimeException('Unable to remove an obsolete generated file: ' . $target);
            }
            $targets[] = ['path' => $path, 'target' => $target];
        }

        foreach ($targets as $target) {
            if (!@unlink($target['target'])) {
                throw new RuntimeException('Unable to remove an obsolete generated file: ' . $target['target']);
            }
            $removed[] = $target['path'];
        }

        return $removed;
    }

    private function absolute(string $root, string $path): string
    {
        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
