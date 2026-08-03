<?php

declare(strict_types=1);

use Fixture\api\PingReq;
use Fixture\api\PingResp;

use function Lumnd\PlatoApiContract\Dsl\get;

return [
    'syntax' => 'v1',
    'description' => 'Ping API',
    'services' => [
        'ping' => get(
            '/ping/index',
            PingReq::class,
            PingResp::class,
            auth: 'none',
            summary: 'Return a ping message',
        ),
    ],
];
