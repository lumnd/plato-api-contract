<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use InvalidArgumentException;

/**
 * A contract body could not be read into request fields or a response schema, with a stable code.
 */
final class SchemaException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $issueCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
