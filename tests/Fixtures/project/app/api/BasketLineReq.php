<?php

declare(strict_types=1);

namespace Fixture\api;

use Lumnd\PlatoApiContract\Dsl\ApiField;

/**
 * One line of a basket: a required identifier, a quantity that has a default, and a flag that is
 * false unless the caller says otherwise.
 */
final readonly class BasketLineReq
{
    public function __construct(
        /** @desc The article number. */
        #[ApiField(maxLength: 8)]
        public string $sku,
        /** @desc How many, one when unsaid. */
        #[ApiField(minimum: 1)]
        public int $qty = 1,
        /** @desc Whether the line is a gift. */
        public bool $gift = false,
    ) {
    }
}
