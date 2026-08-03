<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Dsl;

final readonly class Envelope
{
    public function __construct(
        public string $statusField = 'code',
        public int $successValue = 0,
        public string $messageField = 'msg',
        public string $successMessage = 'successful',
        public string $dataField = 'data',
    ) {
    }
}
