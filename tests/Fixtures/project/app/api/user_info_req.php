<?php

declare(strict_types=1);

namespace Fixture\api;

use Lumnd\PlatoApiContract\Dsl\ApiField;

final readonly class user_info_req
{
    public function __construct(
        /**
         * @desc User ID, positive integer.
         * @must true
         */
        #[ApiField(minimum: 1)]
        public int $id = 0,
    ) {
    }
}
