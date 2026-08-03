<?php

declare(strict_types=1);

namespace App\logic;

use LogicException;
use Lumnd\PlatoApiContract\Runtime\ApiContext;

final class user_login
{
    public static function handle(
        \Fixture\api\LoginReq $request,
        ApiContext $context,
    ): \Fixture\api\LoginResp {
        throw new LogicException('Implement App\logic\user_login::handle().');
    }
}
