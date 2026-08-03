<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Ir;

use InvalidArgumentException;

final readonly class ResponseEnvelope
{
    public function __construct(
        public string $statusField,
        public int $successValue,
        public string $messageField,
        public string $successMessage,
        public string $dataField,
    ) {
        foreach ([$statusField, $messageField, $dataField] as $field) {
            if (preg_match('/^[a-z_][a-z0-9_]*$/', $field) !== 1) {
                throw new InvalidArgumentException('Envelope field names must use lower snake_case.');
            }
        }

        if (count(array_unique([$statusField, $messageField, $dataField])) !== 3) {
            throw new InvalidArgumentException('Envelope field names must be unique.');
        }
    }
}
