<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * Everything that can make identical contracts produce different files.
 *
 * The manifest and `api:check` of iteration 6 consume this. The parts are kept apart on purpose: a
 * project that edits its own template sees `templates` change and nothing else.
 */
final readonly class GenerationFingerprint
{
    public function __construct(
        public string $contracts,
        public string $config,
        public string $adapter,
        public string $templates = '',
    ) {
    }

    public function value(): string
    {
        return hash('sha256', implode("\0", [
            $this->contracts,
            $this->config,
            $this->adapter,
            $this->templates,
        ]));
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'adapter' => $this->adapter,
            'config' => $this->config,
            'contracts' => $this->contracts,
            'templates' => $this->templates,
            'value' => $this->value(),
        ];
    }
}
