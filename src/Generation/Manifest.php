<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use JsonException;
use Lumnd\PlatoApiContract\Exception\GenerationException;

/**
 * What the last run wrote, so this run can tell its own output from a human's edit.
 *
 * Without a record of the bytes it produced, a generator has only two states - "the file matches
 * what I would write" and "it does not" - and the second one covers both a changed contract and a
 * hand-edited file. The manifest separates them, which is the difference between regenerating and
 * destroying somebody's work.
 *
 * It is deterministic and carries no timestamp, so it belongs in version control next to the code it
 * describes and produces a reviewable diff.
 */
final readonly class Manifest
{
    public const VERSION = 1;

    /**
     * @param array<string, ManifestEntry> $entries keyed and ordered by path
     */
    public function __construct(
        public string $adapter,
        public GenerationFingerprint $fingerprint,
        public array $entries,
    ) {
    }

    public static function empty(): self
    {
        return new self('', new GenerationFingerprint('', '', ''), []);
    }

    /**
     * @param list<GeneratedArtifact> $artifacts
     * @param array<string, ManifestEntry> $retainedEntries entries from the previous manifest that
     *                                                      must remain visible even though this run
     *                                                      does not produce their files
     */
    public static function fromArtifacts(
        string $adapter,
        GenerationFingerprint $fingerprint,
        array $artifacts,
        array $retainedEntries = [],
    ): self {
        $entries = $retainedEntries;
        foreach ($artifacts as $artifact) {
            if ($artifact->ownership === Ownership::Tool) {
                // The manifest cannot record its own hash while it is still being built.
                continue;
            }

            $entries[$artifact->path] = new ManifestEntry(
                $artifact->path,
                $artifact->ownership,
                $artifact->ownership->isTracked() ? hash('sha256', $artifact->contents) : null,
            );
        }
        ksort($entries, SORT_STRING);

        return new self($adapter, $fingerprint, $entries);
    }

    /**
     * The manifest of a project that has one, or an empty manifest for a project that has never
     * generated. A manifest that exists but cannot be read is an error: reporting it as empty would
     * blame the project for edits it never made.
     *
     * @throws GenerationException
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            return self::empty();
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new GenerationException(['Unable to read the generation manifest: ' . $path]);
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GenerationException([
                'The generation manifest is not valid JSON: ' . $path . ': ' . $exception->getMessage(),
                'Delete it to start tracking again, or restore it from version control.',
            ]);
        }

        if (!is_array($decoded) || !isset($decoded['artifacts']) || !is_array($decoded['artifacts'])) {
            throw new GenerationException([
                'The generation manifest does not describe any artifacts: ' . $path,
            ]);
        }

        $entries = [];
        /** @var mixed $entry */
        foreach ($decoded['artifacts'] as $artifactPath => $entry) {
            if (!is_string($artifactPath) || !is_array($entry)) {
                continue;
            }
            if (!PathGuard::isSafeRelativePath($artifactPath)) {
                throw new GenerationException([
                    'The generation manifest contains an unsafe artifact path: ' . $artifactPath,
                    'Restore the manifest from version control or delete it to start tracking again.',
                ]);
            }

            $ownership = Ownership::tryFrom(is_string($entry['ownership'] ?? null) ? $entry['ownership'] : '');
            if ($ownership === null) {
                continue;
            }

            $entries[$artifactPath] = new ManifestEntry(
                $artifactPath,
                $ownership,
                is_string($entry['sha256'] ?? null) ? $entry['sha256'] : null,
            );
        }
        ksort($entries, SORT_STRING);

        $fingerprint = is_array($decoded['fingerprint'] ?? null) ? $decoded['fingerprint'] : [];

        return new self(
            is_string($decoded['adapter'] ?? null) ? $decoded['adapter'] : '',
            new GenerationFingerprint(
                contracts: is_string($fingerprint['contracts'] ?? null) ? $fingerprint['contracts'] : '',
                config: is_string($fingerprint['config'] ?? null) ? $fingerprint['config'] : '',
                adapter: is_string($fingerprint['adapter'] ?? null) ? $fingerprint['adapter'] : '',
                templates: is_string($fingerprint['templates'] ?? null) ? $fingerprint['templates'] : '',
            ),
            $entries,
        );
    }

    public function entry(string $path): ?ManifestEntry
    {
        return $this->entries[$path] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * @throws GenerationException
     */
    public function toJson(): string
    {
        $artifacts = [];
        foreach ($this->entries as $path => $entry) {
            $artifacts[$path] = [
                'ownership' => $entry->ownership->value,
                'sha256' => $entry->sha256,
            ];
        }

        try {
            $json = json_encode(
                [
                    'manifest_version' => self::VERSION,
                    'adapter' => $this->adapter,
                    'fingerprint' => $this->fingerprint->toArray(),
                    'artifacts' => $artifacts === [] ? new \stdClass() : $artifacts,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new GenerationException(['Unable to encode the generation manifest: ' . $exception->getMessage()]);
        }

        return $json . "\n";
    }
}
