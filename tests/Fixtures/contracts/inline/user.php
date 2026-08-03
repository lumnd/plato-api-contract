<?php

declare(strict_types=1);

use function Lumnd\PlatoApiContract\Dsl\get;

final readonly class inline_user_req
{
    public function __construct(
        /**
         * @desc User ID.
         * @must true
         */
        public int $id = 0,
    ) {
    }
}

final readonly class inline_user_resp
{
    public function __construct(
        /** @desc User ID. */
        public int $id,
    ) {
    }
}

return [
    'syntax' => 'v1',
    'services' => [
        'inline_user' => [
            /**
             * @title Get user information
             * @desc Get one user by ID.
             */
            get(
                '/inline_user/get_user_info/:id',
                inline_user_req::class,
                inline_user_resp::class,
                handler: 'get_user_info',
                auth: 'required',
            ),
        ],
    ],
];
