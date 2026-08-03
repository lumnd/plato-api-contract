<?php

declare(strict_types=1);

namespace App\logic;

use LogicException;
use Lumnd\PlatoApiContract\Runtime\ApiContext;

final class ping_index
{
    public static function handle(
        \Fixture\api\PingReq $request,
        ApiContext $context,
    ): \Fixture\api\PingResp {
        throw new LogicException('Implement App\logic\ping_index::handle().');
    }
}
