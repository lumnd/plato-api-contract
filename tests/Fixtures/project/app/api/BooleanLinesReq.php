<?php

declare(strict_types=1);

namespace Fixture\api;

use Lumnd\PlatoApiContract\Dsl\ApiField;

/**
 * A request whose array elements insist on a boolean, one level deeper than a flat DTO can hide it.
 */
final readonly class BooleanLinesReq
{
    /** @param list<BooleanReq> $lines */
    public function __construct(
        #[ApiField(items: BooleanReq::class)]
        public array $lines,
    ) {
    }
}
