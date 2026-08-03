<?php

declare(strict_types=1);

namespace App\logic;

use LogicException;
use Lumnd\PlatoApiContract\Runtime\ApiContext;

final class user_get_user_info
{
    public static function handle(
        \Fixture\api\user_info_req $request,
        ApiContext $context,
    ): \Fixture\api\user_info_resp {
        throw new LogicException('Implement App\logic\user_get_user_info::handle().');
    }
}
