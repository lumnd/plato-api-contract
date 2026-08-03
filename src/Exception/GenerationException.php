<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Exception;

use RuntimeException;

/**
 * Generation was refused. No artifact has been written when this is thrown.
 */
final class GenerationException extends RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct($errors === [] ? 'Generation failed.' : implode("\n", $errors));
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
