<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use JsonException;
use RuntimeException;

/**
 * Checks every artifact before a single byte reaches the project.
 */
final class ArtifactVerifier
{
    /**
     * @param list<GeneratedArtifact> $artifacts
     * @return list<string> human readable errors; empty when every artifact is safe and valid
     */
    public function verify(array $artifacts): array
    {
        $errors = [];
        $seen = [];

        foreach ($artifacts as $artifact) {
            if (!PathGuard::isSafeRelativePath($artifact->path)) {
                $errors[] = 'Artifact path escapes the generation root: ' . $artifact->path;
                continue;
            }
            if (isset($seen[$artifact->path]) && $seen[$artifact->path] !== $artifact->contents) {
                $errors[] = 'Two different artifacts claim the same path: ' . $artifact->path;
                continue;
            }
            $seen[$artifact->path] = $artifact->contents;

            if ($artifact->isPhp()) {
                $error = $this->lintPhp($artifact);
                if ($error !== null) {
                    $errors[] = $error;
                }
                continue;
            }
            if ($artifact->isJson()) {
                try {
                    json_decode($artifact->contents, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    $errors[] = 'Generated JSON is invalid in ' . $artifact->path . ': '
                        . $exception->getMessage();
                }
            }
        }

        return $errors;
    }

    private function lintPhp(GeneratedArtifact $artifact): ?string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'api-contract-lint-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to create a temporary file for php -l.');
        }

        try {
            if (file_put_contents($temporary, $artifact->contents) === false) {
                throw new RuntimeException('Unable to write a temporary file for php -l.');
            }

            $output = [];
            $status = 0;
            exec(
                escapeshellarg(PHP_BINARY) . ' -n -l ' . escapeshellarg($temporary) . ' 2>&1',
                $output,
                $status,
            );
            if ($status === 0) {
                return null;
            }

            $message = str_replace($temporary, $artifact->path, implode("\n", $output));

            return 'Generated PHP is invalid in ' . $artifact->path . ': ' . $message;
        } finally {
            @unlink($temporary);
        }
    }
}
