<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Runtime\Refusal;

/**
 * Checks the class a project registered to refuse invalid input with.
 *
 * The class belongs to the application being generated for, so it is asked for once, before
 * anything is generated, rather than fataling in a controller the first time a caller sends a bad
 * request. Same reasoning as PlatoStrategies, and the same shape of message.
 */
final class PlatoRefusal
{
    private function __construct()
    {
    }

    /**
     * @throws GenerationException when the registered class cannot serve
     */
    public static function verify(PlatoConfig $config): void
    {
        $class = $config->exception;

        if ($class === null) {
            return;
        }

        $error = self::error(ltrim($class, '\\'));

        if ($error !== null) {
            throw new GenerationException(['Refusal exception: ' . $error]);
        }
    }

    /** What is wrong with this class, or null when nothing is. */
    private static function error(string $class): ?string
    {
        if (!class_exists($class)) {
            return $class . ' does not exist. Autoloading is the project\'s, so check the class is '
                . 'reachable from the composer autoloader this command runs with.';
        }

        if (!is_subclass_of($class, Refusal::class)) {
            return $class . ' does not implement ' . Refusal::class . ', so nothing says what a '
                . 'refused request answers. Give it a static refuse(array $errors) returning the '
                . 'throwable to raise.';
        }

        return null;
    }
}
