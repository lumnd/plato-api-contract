<?php

declare(strict_types=1);

namespace Fixture\api;

/**
 * The properties are declared code first on purpose: segment order must follow the path, not the
 * constructor.
 */
final readonly class SegmentReq
{
    public function __construct(
        public int $code,
        public int $id,
    ) {
    }
}
