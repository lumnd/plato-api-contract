<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Exception;

use Lumnd\PlatoApiContract\Contract\ContractIssue;
use RuntimeException;

final class ContractValidationException extends RuntimeException
{
    /** @param non-empty-list<ContractIssue> $issues */
    public function __construct(
        private readonly array $issues,
    ) {
        parent::__construct(implode(PHP_EOL, array_map(
            static fn (ContractIssue $issue): string => $issue->format(),
            $issues,
        )));
    }

    /** @return non-empty-list<ContractIssue> */
    public function issues(): array
    {
        return $this->issues;
    }
}
