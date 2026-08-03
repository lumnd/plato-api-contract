<?php

declare(strict_types=1);

namespace Fixture\logic;

use Fixture\api\PingReq;
use Fixture\api\PingResp;
use Lumnd\PlatoApiContract\Runtime\ApiContext;

/**
 * A hand-written Logic implementation. Generation creates this file once and never overwrites it,
 * so the HTTP feature test drives real user code behind the generated controller.
 */
final class ping_index
{
    public static function handle(PingReq $request, ApiContext $context): PingResp
    {
        return new PingResp(message: 'pong:' . $request->message);
    }
}
