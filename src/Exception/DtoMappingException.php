<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Exception;

use InvalidArgumentException;

final class DtoMappingException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $path,
        string $message,
    ) {
        parent::__construct($path . ': ' . $message);
    }
}
