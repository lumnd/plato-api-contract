<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use InvalidArgumentException;

/**
 * The framework-neutral input of one generation run.
 */
final readonly class GenerationContext
{
    public function __construct(
        public string $root,
        public GenerationConfig $config = new GenerationConfig(),
    ) {
        if ($root === '') {
            throw new InvalidArgumentException('The generation root must not be empty.');
        }
    }
}
