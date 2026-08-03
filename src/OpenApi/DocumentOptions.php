<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\OpenApi;

use Lumnd\PlatoApiContract\Generation\GenerationConfig;

/**
 * Document level settings. The OpenAPI layer depends on this instead of any platform configuration,
 * so the document is identical whichever adapter generates the code.
 */
final readonly class DocumentOptions
{
    public function __construct(
        public string $basePath = '',
        public string $pathSuffix = '',
        public ?string $title = null,
        public ?string $version = null,
    ) {
    }

    public static function fromConfig(GenerationConfig $config): self
    {
        return new self($config->basePath, $config->pathSuffix, $config->title, $config->version);
    }
}
