<?php

declare(strict_types=1);

namespace Fixture\api;

use Lumnd\PlatoApiContract\Dsl\ApiField;

/**
 * A request holding the shapes a flat DTO cannot describe: an array of structures, an array that
 * may be null, and a nullable nested DTO with a required property.
 */
final readonly class BasketReq
{
    /**
     * @param list<BasketLineReq> $lines
     * @param list<string>|null $tags
     */
    public function __construct(
        /** @desc The lines of the basket. */
        #[ApiField(items: BasketLineReq::class)]
        public array $lines,
        /** @desc Free-form labels, or nothing at all. */
        #[ApiField(items: 'string')]
        public ?array $tags = null,
        public ?BasketBuyerReq $buyer = null,
    ) {
    }
}
