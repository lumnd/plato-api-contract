<?php

declare(strict_types=1);

use Fixture\api\AccountResp;
use Fixture\api\LoginReq;
use Fixture\api\LoginResp;
use Fixture\api\UserInfoReq;
use Fixture\api\user_info_req;
use Fixture\api\user_info_resp;

use function Lumnd\PlatoApiContract\Dsl\get;
use function Lumnd\PlatoApiContract\Dsl\post;

return [
    'syntax' => 'v1',
    'description' => 'User API',
    'services' => [
        'user' => [
            post(
                '/user/login',
                LoginReq::class,
                LoginResp::class,
                handler: 'Login',
                auth: 'none',
            ),
            /**
             * @title "Get user information"
             * @desc "Get the signed-in user's detailed information."
             */
            get(
                '/user/get_user_info/:id',
                user_info_req::class,
                user_info_resp::class,
                handler: 'get_user_info',
                auth: 'required',
            ),
            /**
             * @title "Disable an account"
             * @desc "A write operation that requires an authenticated identity."
             */
            post(
                '/user/disable_account',
                UserInfoReq::class,
                AccountResp::class,
                handler: 'disable_account',
                auth: 'required',
            ),
        ],
    ],
];
