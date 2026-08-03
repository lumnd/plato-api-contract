<?php

declare(strict_types=1);

namespace Fixture\contract;

use Lumnd\PlatoApiContract\Runtime\Refusal;
use RuntimeException;
use Throwable;

/**
 * A project whose refusals are all exceptions, this one included.
 *
 * Stands in for the real thing: an application with one failure mechanism and one middleware that
 * renders it, where a request refused for its input has to arrive by the same road as a request
 * refused by a business rule.
 */
final class refusal_exception extends RuntimeException implements Refusal
{
    /** @var array<string, string> */
    public array $errors = [];

    public static function refuse(array $errors): Throwable
    {
        $exception = new self('invalid input', -2);
        $exception->errors = $errors;

        return $exception;
    }
}
