<?php

declare(strict_types=1);

use Fixture\api\invalid_must_req;
use Fixture\api\user_info_resp;

use function Lumnd\PlatoApiContract\Dsl\get;

return [
    'syntax' => 'v1',
    'services' => [
        'user' => get(
            '/user/get_user_info/:id',
            invalid_must_req::class,
            user_info_resp::class,
            handler: 'get_user_info',
            auth: 'required',
        ),
    ],
];
