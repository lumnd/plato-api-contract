<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Ir\Field;

/**
 * How contract constraints become the target framework's validation rules.
 */
interface ValidationRules
{
    /**
     * The rules of one field, keyed by the name the validator addresses the value under.
     *
     * A field is usually one entry under its own name, but a nested one is as many entries as it
     * has constrained leaves, so a rule set describing `user.name` still reaches the validator.
     *
     * @return array<string, list<string>> empty when nothing about the field can be checked
     */
    public function forField(Field $field): array;

    /**
     * A PHP expression that validates `$input` and yields a validator object exposing
     * fails(), errors() and validated().
     */
    public function validatorExpression(string $input, string $rules): string;

    /**
     * A complete PHP statement, semicolon included, answering a failed validation.
     *
     * A statement rather than an expression because refusing is not always answering: an
     * application that registered an exception class throws from here instead, and the response is
     * written where every other refusal of that application is written.
     */
    public function failureStatement(string $validator, PlatoConfig $config): string;

    /** @return list<string> */
    public function imports(): array;
}
