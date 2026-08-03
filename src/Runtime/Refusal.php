<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Runtime;

use Throwable;

/**
 * How a project refuses input that does not satisfy the contract.
 *
 * An application that already has one failure mechanism -- a business exception its error
 * middleware renders into its own envelope -- should refuse invalid input the same way, rather than
 * through a second path that a generated controller writes by hand. Naming the class here is what
 * lets the built-in action template do that:
 *
 *     'api_contract' => [
 *         'exception' => common\exception\biz_exception::class,
 *     ],
 *
 * and the generated action becomes `throw common\exception\biz_exception::refuse($validator->errors())`.
 *
 * A factory rather than a constructor signature, because the status code, the message and where the
 * field errors go in the response are the application's to decide and this package has no vocabulary
 * for any of them. It is checked before anything is generated, so a class that cannot serve is a
 * configuration error and not a fatal inside a template.
 *
 * Nothing is registered by default: without this the generated action answers 422 with the errors,
 * which needs no application code at all.
 */
interface Refusal
{
    /**
     * The throwable that refuses this input.
     *
     * @param array<string, string> $errors field name => message, one per field, as the validator
     *                                      reports them
     */
    public static function refuse(array $errors): Throwable;
}
