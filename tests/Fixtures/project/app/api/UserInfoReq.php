<?php

declare(strict_types=1);

namespace Fixture\api;

use Lumnd\PlatoApiContract\Dsl\ApiField;

final readonly class UserInfoReq
{
    public function __construct(
        /**
         * @desc User ID, positive integer.
         * @must true
         */
        #[ApiField(minimum: 1)]
        public int $id,
    ) {
    }
}
