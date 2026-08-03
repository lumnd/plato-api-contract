<?php

declare(strict_types=1);

namespace Tests\Golden;

use Lumnd\PlatoApiContract\Generation\GeneratedArtifact;
use RuntimeException;

/**
 * Frozen, human-reviewable baselines for every generated artifact.
 *
 * Golden files are never rewritten by a normal test run. Set PLATO_GOLDEN_UPDATE=1 to rewrite them
 * and review the resulting diff by hand, as required by ADR 0004.
 */
final class GoldenFiles
{
    /**
     * @param list<GeneratedArtifact> $artifacts
     * @return array<string, string>
     */
    public static function index(array $artifacts): array
    {
        $files = [];
        foreach ($artifacts as $artifact) {
            if (isset($files[$artifact->path]) && $files[$artifact->path] !== $artifact->contents) {
                throw new RuntimeException('Conflicting artifacts for path: ' . $artifact->path);
            }
            $files[$artifact->path] = $artifact->contents;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    public static function directory(string $case): string
    {
        return __DIR__ . '/plato/' . $case;
    }

    public static function updating(): bool
    {
        return getenv('PLATO_GOLDEN_UPDATE') === '1';
    }

    /**
     * @param array<string, string> $files
     */
    public static function write(string $case, array $files): void
    {
        $base = self::directory($case);
        foreach ($files as $path => $contents) {
            $target = $base . '/' . $path;
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create golden directory: ' . $directory);
            }
            file_put_contents($target, $contents);
        }

        foreach (self::stored($case) as $path) {
            if (!isset($files[$path])) {
                unlink($base . '/' . $path);
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function stored(string $case): array
    {
        $base = self::directory($case);
        if (!is_dir($base)) {
            return [];
        }

        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            }
        }
        sort($paths, SORT_STRING);

        return $paths;
    }

    public static function read(string $case, string $path): string
    {
        $target = self::directory($case) . '/' . $path;
        $contents = is_file($target) ? file_get_contents($target) : false;

        return $contents === false ? '' : $contents;
    }
}
