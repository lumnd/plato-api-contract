<?php

declare(strict_types=1);

use Fixture\api\UserInfoReq;
use Fixture\api\UserInfoResp;

use function Lumnd\PlatoApiContract\Dsl\get;

/**
 * @title
 * @desc This comment is not attached to the endpoint.
 * @unknown unsupported
 */
$unrelated = true;

return [
    'syntax' => 'v1',
    'services' => [
        'user' => get(
            '/user/get_user_info/:id',
            UserInfoReq::class,
            UserInfoResp::class,
            auth: 'required',
        ),
    ],
];
