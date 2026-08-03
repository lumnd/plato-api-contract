<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use InvalidArgumentException;

/**
 * One file produced by a platform adapter or by the pipeline itself.
 *
 * The path is always relative to the generation root and always uses forward slashes, so an adapter
 * or a template can never write outside the configured project. Ownership says who the file belongs
 * to once it is there, which is what decides whether a later run may replace or remove it.
 */
final readonly class GeneratedArtifact
{
    public function __construct(
        public string $path,
        public string $contents,
        public Ownership $ownership,
    ) {
        if ($path === '') {
            throw new InvalidArgumentException('An artifact path must not be empty.');
        }
    }

    public function isPhp(): bool
    {
        return str_ends_with($this->path, '.php');
    }

    public function isJson(): bool
    {
        return str_ends_with($this->path, '.json');
    }
}
