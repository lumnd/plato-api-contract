<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Exception;

use InvalidArgumentException;

/**
 * A path template could not be normalized.
 *
 * The reason is a framework-neutral slug; the contract layer maps it onto a stable diagnostic code.
 */
final class PathTemplateException extends InvalidArgumentException
{
    public const SYNTAX = 'syntax';
    public const PARAMETER_NAME = 'parameter_name';
    public const PARAMETER_DUPLICATE = 'parameter_duplicate';

    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }
}
