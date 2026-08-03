<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Exception;

use RuntimeException;
use Throwable;

/**
 * A template could not be found, read or rendered.
 *
 * Template problems are configuration problems: they name the template, the directories that were
 * searched and the file that failed, so a project can fix its own override without reading a stack
 * trace.
 */
final class TemplateException extends RuntimeException
{
    public static function unknownDirectory(string $directory): self
    {
        return new self('Template directory does not exist: ' . $directory);
    }

    public static function invalidName(string $name): self
    {
        return new self(
            'Invalid template name "' . $name . '"; template names are lowercase identifiers such as "controller".',
        );
    }

    /**
     * @param list<string> $directories
     */
    public static function missing(string $name, array $directories): self
    {
        return new self(sprintf(
            'Template "%s" was not found. Searched: %s.',
            $name,
            $directories === [] ? '(no template directory)' : implode(', ', $directories),
        ));
    }

    public static function unreadable(string $name, string $file): self
    {
        return new self('Unable to read the template "' . $name . '" from ' . $file . '.');
    }

    public static function failed(string $name, string $file, Throwable $previous): self
    {
        return new self(
            'The template "' . $name . '" (' . $file . ') failed: ' . $previous->getMessage(),
            0,
            $previous,
        );
    }
}
