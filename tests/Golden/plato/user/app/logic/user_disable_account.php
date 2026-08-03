<?php

declare(strict_types=1);

namespace App\logic;

use LogicException;
use Lumnd\PlatoApiContract\Runtime\ApiContext;

final class user_disable_account
{
    public static function handle(
        \Fixture\api\UserInfoReq $request,
        ApiContext $context,
    ): \Fixture\api\AccountResp {
        throw new LogicException('Implement App\logic\user_disable_account::handle().');
    }
}
