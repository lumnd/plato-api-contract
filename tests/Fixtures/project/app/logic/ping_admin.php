<?php

declare(strict_types=1);

namespace Fixture\logic;

use Fixture\api\PingReq;
use Fixture\api\PingResp;
use Fixture\auth\identity;
use Lumnd\PlatoApiContract\Runtime\ApiContext;

/**
 * An authenticated operation using the same route-level requirement as every private API.
 */
final class ping_admin
{
    public static function handle(PingReq $request, ApiContext $context): PingResp
    {
        $auth = $context->auth;

        return new PingResp(
            message: $request->message . ':' . ($auth instanceof identity ? $auth->name : 'anonymous') . ':admin',
        );
    }
}
