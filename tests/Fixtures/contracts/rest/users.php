<?php

declare(strict_types=1);

use function Lumnd\PlatoApiContract\Dsl\get;
use function Lumnd\PlatoApiContract\Dsl\rules;

return [
    'syntax' => 'v1',
    'services' => [
        'users' => get(
            '/organizations/{organization}/users/{user}',
            rules([
                'organization' => ['required', 'integer', 'from:segment'],
                'user' => ['required', 'integer', 'from:segment'],
            ]),
            rules([
                'id' => ['integer'],
                'name' => ['string'],
            ]),
            handler: 'show',
            auth: 'none',
        ),
    ],
];
