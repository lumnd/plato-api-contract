<?php

declare(strict_types=1);

namespace Fixture\api;

final readonly class invalid_must_req
{
    public function __construct(
        /**
         * @desc Invalid field metadata.
         * @must required
         */
        public int $id,
    ) {
    }
}
