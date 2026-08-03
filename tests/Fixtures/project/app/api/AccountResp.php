<?php

declare(strict_types=1);

namespace Fixture\api;

use JsonSerializable;
use Lumnd\PlatoApiContract\Dsl\ApiField;

final readonly class AccountResp implements JsonSerializable
{
    /** @param list<RoleResp> $roles */
    public function __construct(
        public int $id,
        public bool $enabled,
        public ProfileResp $profile,
        #[ApiField(items: RoleResp::class)]
        public array $roles,
        public AccountStatus $status,
        public ?string $note = null,
    ) {
    }

    /** @return array{secret: string} */
    public function jsonSerialize(): array
    {
        return ['secret' => 'must not reach the response'];
    }
}
