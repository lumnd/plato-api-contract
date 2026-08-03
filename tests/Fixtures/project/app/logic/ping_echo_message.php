<?php

declare(strict_types=1);

namespace Fixture\logic;

use Lumnd\PlatoApiContract\Runtime\ApiContext;

/**
 * A hand-written Logic implementation for the rules() form of the contract.
 *
 * It reads every declared key straight, with no `??` of its own: the point of the projection is
 * that an optional field is there, holding its declared default or null, whether or not the caller
 * sent it and whether or not it carried a rule the validator could run.
 */
final class ping_echo_message
{
    /**
     * @param array{message: string, loud: bool, note: string|null} $request
     * @return array{message: string, note: string|null}
     */
    public static function handle(array $request, ApiContext $context): array
    {
        return [
            'message' => $request['loud'] ? strtoupper($request['message']) : $request['message'],
            'note' => $request['note'],
        ];
    }
}
