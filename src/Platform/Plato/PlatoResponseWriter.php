<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use Lumnd\PlatoApiContract\Ir\ResponseEnvelope;

/**
 * The response DTO array is wrapped in the contract's envelope, `{code: 0, msg: ..., data: ...}`
 * by default.
 *
 * That envelope is `resp::response()`, which plato ships and calls the classic one: the same three
 * fields, and the call every other answer of an application built on it goes through -- a refusal
 * from a business rule, a failure its error middleware caught. Writing the array out here instead
 * would answer a success in a shape nothing else in that application produces, and would freeze the
 * message at generation time, in one language, for an application that answers in several. So the
 * message is left to `resp::response()` unless the contract states one of its own.
 *
 * A contract that renamed the envelope's fields is not that envelope any more, and is written as a
 * plain json body at the operation's declared status.
 */
final class PlatoResponseWriter implements ResponseWriter
{
    /** The envelope resp::response() writes, which cannot be told to write another. */
    private const FIELDS = ['code', 'msg', 'data'];

    /** The message resp::response() fills in when it is not given one. */
    private const MESSAGE = 'successful';

    public function expression(ResponseEnvelope $envelope, string $data, int $status): string
    {
        if ([$envelope->statusField, $envelope->messageField, $envelope->dataField] !== self::FIELDS) {
            return $this->json($envelope, $data, $status);
        }

        $arguments = [(string) $envelope->successValue, $data];

        if ($envelope->successMessage !== self::MESSAGE) {
            $arguments[] = var_export($envelope->successMessage, true);
        }

        return 'resp::response(' . implode(', ', $arguments) . ')';
    }

    private function json(ResponseEnvelope $envelope, string $data, int $status): string
    {
        return 'resp::json(['
            . var_export($envelope->statusField, true) . ' => ' . $envelope->successValue . ', '
            . var_export($envelope->messageField, true) . ' => '
            . var_export($envelope->successMessage, true) . ', '
            . var_export($envelope->dataField, true) . ' => ' . $data
            . '], ' . $status . ')';
    }

    public function returnType(): string
    {
        return 'reply';
    }

    public function imports(): array
    {
        return ['plato\\http\\reply', 'plato\\http\\resp'];
    }
}
