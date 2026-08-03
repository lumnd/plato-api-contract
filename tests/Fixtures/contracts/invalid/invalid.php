<?php

declare(strict_types=1);

use Fixture\api\LoginReq;
use Fixture\api\LoginResp;

use function Lumnd\PlatoApiContract\Dsl\get;
use function Lumnd\PlatoApiContract\Dsl\post;

/**
 * Every failure this file declares is intentional; the lint test asserts the diagnostic codes.
 */
return [
    'syntax' => 'v1',
    // structure.unknown_key
    'actions' => [],
    'services' => [
        'broken' => [
            get('/broken/index', LoginReq::class, LoginResp::class, auth: 'none'),
            // operation.name_duplicate
            get('/broken/index', LoginReq::class, LoginResp::class, auth: 'none'),
            post('/broken/create', LoginReq::class, LoginResp::class),
            // dto.path_parameter_missing
            get('/broken/show/{id}', LoginReq::class, LoginResp::class, auth: 'none'),
            // A generic route convention allows an explicit handler independent of the URL.
            get('/broken/list', LoginReq::class, LoginResp::class, handler: 'other', auth: 'none'),
        ],
        // contract.name
        'Bad_Name' => get('/Bad_Name/index', LoginReq::class, LoginResp::class, auth: 'none'),
    ],
];
