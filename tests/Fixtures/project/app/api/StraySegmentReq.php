<?php

declare(strict_types=1);

namespace Fixture\api;

use Lumnd\PlatoApiContract\Dsl\ApiField;

/**
 * Declares a segment field that no path can provide.
 */
final readonly class StraySegmentReq
{
    public function __construct(
        #[ApiField(source: 'segment')]
        public int $code,
    ) {
    }
}
