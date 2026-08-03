<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use InvalidArgumentException;

/**
 * Everything the pipeline needs that is not specific to one platform adapter.
 *
 * Adapter specific settings (namespaces, output directories, naming) belong to the adapter's own
 * configuration object, so adding a framework never widens this class.
 */
final readonly class GenerationConfig
{
    public function __construct(
        public string $openApiPath = 'docs/api/openapi.json',
        public string $basePath = '',
        public string $pathSuffix = '',
        public string $title = 'API',
        public string $version = '1.0.0',
        public string $manifestPath = 'api/manifest.json',
    ) {
        if ($openApiPath === '' || !PathGuard::isSafeRelativePath($openApiPath)) {
            throw new InvalidArgumentException(
                'The OpenAPI path must be a relative path inside the project: ' . $openApiPath,
            );
        }
        if ($manifestPath === '' || !PathGuard::isSafeRelativePath($manifestPath)) {
            throw new InvalidArgumentException(
                'The manifest path must be a relative path inside the project: ' . $manifestPath,
            );
        }
        if ($manifestPath === $openApiPath) {
            throw new InvalidArgumentException(
                'The manifest and the OpenAPI document cannot share a path: ' . $manifestPath,
            );
        }
        if ($title === '') {
            throw new InvalidArgumentException('The OpenAPI document title must not be empty.');
        }
        if ($version === '') {
            throw new InvalidArgumentException('The OpenAPI document version must not be empty.');
        }
    }

    /**
     * A stable hash of everything in this object, for the generation fingerprint.
     */
    public function hash(): string
    {
        return hash('sha256', implode("\0", [
            $this->openApiPath,
            $this->basePath,
            $this->pathSuffix,
            $this->title,
            $this->version,
            $this->manifestPath,
        ]));
    }
}
