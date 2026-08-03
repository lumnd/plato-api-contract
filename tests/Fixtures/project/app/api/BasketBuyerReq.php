<?php

declare(strict_types=1);

namespace Fixture\api;

final readonly class BasketBuyerReq
{
    public function __construct(
        public string $name,
    ) {
    }
}
