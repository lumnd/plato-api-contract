<?php

declare(strict_types=1);

use Fixture\api\AccountResp;
use Fixture\api\AccountStatus;
use Fixture\api\ProfileResp;
use Fixture\api\RoleResp;
use Lumnd\PlatoApiContract\Exception\DtoMappingException;
use Lumnd\PlatoApiContract\Runtime\Dto;

it('projects nested arrays and DTO list items onto the response DTO contract', function () {
    $existingRole = new RoleResp(2, 'editor');

    $response = Dto::fromArray(AccountResp::class, [
        'id' => 10,
        'enabled' => true,
        'password_hash' => 'discarded',
        'profile' => [
            'nickname' => 'James',
            'private_phone' => 'discarded',
        ],
        'roles' => [
            ['id' => 1, 'name' => 'admin', 'internal_flag' => true],
            $existingRole,
        ],
        'status' => 'active',
    ]);

    expect($response)->toBeInstanceOf(AccountResp::class)
        ->and($response->profile)->toBeInstanceOf(ProfileResp::class)
        ->and($response->roles)->toHaveCount(2)
        ->and($response->roles[0])->toBeInstanceOf(RoleResp::class)
        ->and($response->roles[1])->toBe($existingRole)
        ->and($response->status)->toBe(AccountStatus::Active)
        ->and($response->note)->toBeNull()
        ->and(Dto::toArray($response, AccountResp::class))->toBe([
            'id' => 10,
            'enabled' => true,
            'profile' => ['nickname' => 'James'],
            'roles' => [
                ['id' => 1, 'name' => 'admin'],
                ['id' => 2, 'name' => 'editor'],
            ],
            'status' => 'active',
            'note' => null,
        ]);
});

it('rejects scalar coercion with the complete nested field path', function () {
    expect(fn () => Dto::fromArray(AccountResp::class, [
        'id' => 10,
        'enabled' => true,
        'profile' => ['nickname' => 'James'],
        'roles' => [['id' => '1', 'name' => 'admin']],
        'status' => 'active',
    ]))->toThrow(DtoMappingException::class, 'AccountResp.roles[0].id: Expected int, got string.');
});

it('rejects associative arrays where the DTO contract declares a list', function () {
    expect(fn () => Dto::fromArray(AccountResp::class, [
        'id' => 10,
        'enabled' => true,
        'profile' => ['nickname' => 'James'],
        'roles' => ['admin' => ['id' => 1, 'name' => 'admin']],
        'status' => 'active',
    ]))->toThrow(DtoMappingException::class, 'AccountResp.roles: Expected a list, got an associative array.');
});

it('validates manually constructed DTO lists before normalization', function () {
    $reflection = new ReflectionClass(AccountResp::class);
    $response = $reflection->newInstanceArgs([
        'id' => 10,
        'enabled' => true,
        'profile' => new ProfileResp('James'),
        'roles' => [123],
        'status' => AccountStatus::Active,
    ]);

    expect(fn () => Dto::toArray($response))
        ->toThrow(DtoMappingException::class, 'AccountResp.roles[0]: Expected Fixture\\api\\RoleResp, got int.');
});

it('requires exact primitive types instead of using PHP scalar coercion', function () {
    expect(fn () => Dto::fromArray(AccountResp::class, [
        'id' => '10',
        'enabled' => true,
        'profile' => ['nickname' => 'James'],
        'roles' => [],
        'status' => 'active',
    ]))->toThrow(DtoMappingException::class, 'AccountResp.id: Expected int, got string.');

    expect(fn () => Dto::fromArray(AccountResp::class, [
        'id' => 10,
        'enabled' => 1,
        'profile' => ['nickname' => 'James'],
        'roles' => [],
        'status' => 'active',
    ]))->toThrow(DtoMappingException::class, 'AccountResp.enabled: Expected bool, got int.');
});
