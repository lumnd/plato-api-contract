<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * The single rule for where generated output may land.
 *
 * Adapters and, from iteration 4, user templates provide artifact paths. None of them may escape the
 * configured generation root, use absolute paths, or rely on platform specific separators.
 */
final class PathGuard
{
    public static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return false;
        }
        if (preg_match('#^[A-Za-z]:#', $path) === 1) {
            return false;
        }
        if (str_contains($path, '\\')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
