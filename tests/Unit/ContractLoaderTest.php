<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Contract\ContractCompiler;
use Lumnd\PlatoApiContract\Contract\ContractFile;
use Lumnd\PlatoApiContract\Contract\ContractLoader;
use Lumnd\PlatoApiContract\Exception\ContractValidationException;
use Lumnd\PlatoApiContract\Platform\Plato\Console\ApiLintCommand;
use PHPUnit\Framework\Assert;

use function Lumnd\PlatoApiContract\Dsl\envelope;
use function Lumnd\PlatoApiContract\Dsl\post;

it('compiles endpoints and readonly DTOs into typed IR', function () {
    $user = fixture_contracts('user')->api('user');

    expect($user)->not->toBeNull()
        ->and($user?->envelope->statusField)->toBe('code')
        ->and($user?->envelope->successValue)->toBe(0)
        ->and($user?->envelope->successMessage)->toBe('successful')
        ->and($user?->operations)->toHaveCount(3)
        ->and($user?->operations[0]->requestClass)->toBe(Fixture\api\LoginReq::class)
        ->and($user?->operations[0]->primaryResponse()->dataClass)->toBe(Fixture\api\LoginResp::class)
        ->and($user?->operations[0]->requestFields[0]->source)->toBe('json')
        ->and($user?->operations[1]->action)->toBe('get_user_info')
        ->and($user?->operations[1]->requestClass)->toBe(Fixture\api\user_info_req::class)
        ->and($user?->operations[1]->primaryResponse()->dataClass)->toBe(Fixture\api\user_info_resp::class)
        ->and($user?->operations[1]->summary)->toBe('Get user information')
        ->and($user?->operations[1]->description)->toBe("Get the signed-in user's detailed information.")
        ->and($user?->operations[1]->requestFields[0]->source)->toBe('segment')
        ->and($user?->operations[1]->requestFields[0]->segmentIndex)->toBe(0)
        ->and($user?->operations[1]->requestFields[0]->required)->toBeTrue()
        ->and($user?->operations[1]->requestFields[0]->hasDefault)->toBeFalse()
        ->and($user?->operations[1]->requestFields[0]->minimum)->toBe(1)
        ->and($user?->operations[1]->requestFields[0]->description)->toBe('User ID, positive integer.')
        ->and($user?->operations[1]->primaryResponse()->data->properties['username']->description)
        ->toBe('User name.')
        ->and($user?->operations[1]->primaryResponse()->data->requiredProperties)
        ->toBe(['email', 'id', 'username']);
});

it('discovers every contract file below the directory', function () {
    $contracts = fixture_contracts('user');

    expect($contracts->apis)->toHaveCount(1)
        ->and($contracts->api('user'))->not->toBeNull()
        ->and($contracts->api('absent'))->toBeNull();
});

it('lets a project replace the default code envelope field with status', function () {
    $issues = [];
    $apis = (new ContractCompiler())->compile(
        new ContractFile('/virtual/status.php', [
            'syntax' => 'v1',
            'envelope' => envelope('status'),
            'services' => [
                'status_api' => post(
                    '/status_api/index',
                    Fixture\api\LoginReq::class,
                    Fixture\api\LoginResp::class,
                    auth: 'none',
                ),
            ],
        ]),
        $issues,
    );

    expect($issues)->toBe([])
        ->and($apis[0]->envelope->statusField)->toBe('status');
});

it('validates endpoint PHPDoc as part of contract linting', function () {
    try {
        (new ContractLoader())->loadDirectory(contract_fixture('invalid-doc'));
        Assert::fail('Expected invalid endpoint PHPDoc to fail lint.');
    } catch (ContractValidationException $exception) {
        $codes = array_map(
            static fn ($issue): string => $issue->code,
            $exception->issues(),
        );

        expect($codes)->toContain(
            'endpoint.doc_empty_tag',
            'endpoint.doc_unknown_tag',
            'endpoint.doc_not_attached',
        );
    }
});

it('validates field PHPDoc must flags', function () {
    expect(fn () => (new ContractLoader())->loadDirectory(contract_fixture('invalid-field-doc')))
        ->toThrow(
            ContractValidationException::class,
            'PHPDoc tag @must must be either true or false.',
        );
});

it('distinguishes inline DTO field PHPDoc from endpoint PHPDoc', function () {
    $api = fixture_contracts('inline')->api('inline_user');

    expect($api?->operations[0]->summary)->toBe('Get user information')
        ->and($api?->operations[0]->description)->toBe('Get one user by ID.')
        ->and($api?->operations[0]->requestFields[0]->description)->toBe('User ID.')
        ->and($api?->operations[0]->requestFields[0]->required)->toBeTrue()
        ->and($api?->operations[0]->primaryResponse()->data->properties['id']->description)
        ->toBe('User ID.');
});

it('reports semantic failures with stable codes, files and endpoint paths', function () {
    try {
        (new ContractLoader())->loadDirectory(contract_fixture('invalid'));
        Assert::fail('Expected invalid contracts to fail lint.');
    } catch (ContractValidationException $exception) {
        $issues = $exception->issues();
        $codes = array_column(array_map(
            static fn ($issue): array => $issue->toArray(),
            $issues,
        ), 'code');

        expect($codes)->toContain(
            'structure.unknown_key',
            'operation.name_duplicate',
            'dto.path_parameter_missing',
            'contract.name',
        )->and($issues[0]->file)->toEndWith('/invalid.php')
            ->and($exception->getMessage())->toContain('$.services.broken[');
    }
});

it('rejects a contract file that does not return an array', function () {
    expect(fn () => (new ContractLoader())->loadDirectory(contract_fixture('not-array')))
        ->toThrow(ContractValidationException::class, 'Contract files must return a PHP array.');
});

it('reports an empty or missing contract directory without writing output', function () {
    $missing = contract_fixture('missing');

    expect(fn () => (new ContractLoader())->loadDirectory($missing))
        ->toThrow(ContractValidationException::class, 'Contract directory does not exist.')
        ->and(file_exists($missing))->toBeFalse();
});

it('exposes api lint through the PlatoPHP command contract', function () {
    expect(ApiLintCommand::names())->toBe([
        'api:lint' => 'Validate API contract files without generating output',
    ])->and(ApiLintCommand::requires())->toBe([]);
});
