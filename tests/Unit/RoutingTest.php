<?php

declare(strict_types=1);

use Fixture\api\PingResp;
use Fixture\api\SegmentReq;
use Fixture\api\StraySegmentReq;
use Fixture\api\user_info_req;
use Lumnd\PlatoApiContract\Contract\ContractCompiler;
use Lumnd\PlatoApiContract\Contract\ContractFile;
use Lumnd\PlatoApiContract\Exception\PathTemplateException;
use Lumnd\PlatoApiContract\Ir\AuthMode;
use Lumnd\PlatoApiContract\Ir\Field;
use Lumnd\PlatoApiContract\Ir\Operation;
use Lumnd\PlatoApiContract\Ir\PathTemplate;
use Lumnd\PlatoApiContract\Ir\Response;
use Lumnd\PlatoApiContract\Ir\Schema;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoRouteConvention;

use function Lumnd\PlatoApiContract\Dsl\get;

/**
 * Compile one endpoint and return either its operation or the diagnostic codes it produced.
 *
 * @param class-string $request
 * @return array{Operation|null, list<string>}
 */
function routing_compile(string $path, string $request = user_info_req::class, string $service = 'user'): array
{
    $issues = [];
    $apis = (new ContractCompiler(routes: new PlatoRouteConvention()))->compile(
        new ContractFile('/virtual/user.php', [
            'syntax' => 'v1',
            'services' => [
                $service => get($path, $request, PingResp::class, auth: 'none'),
            ],
        ]),
        $issues,
    );

    return [
        $apis[0]->operations[0] ?? null,
        array_map(static fn ($issue): string => $issue->code, $issues),
    ];
}

/**
 * @param class-string $request
 * @return list<string>
 */
function routing_codes(string $path, string $request = user_info_req::class, string $service = 'user'): array
{
    return routing_compile($path, $request, $service)[1];
}

it('normalizes both path spellings onto one canonical value', function () {
    expect(PathTemplate::parse('/user/get_user_info/:id')->value)->toBe('/user/get_user_info/{id}')
        ->and(PathTemplate::parse('/user/get_user_info/{id}')->value)->toBe('/user/get_user_info/{id}')
        ->and(PathTemplate::parse('/user/get_user_info/:id')->parameters)->toBe(['id']);
});

it('expresses a nested REST path without losing order or literals', function () {
    $path = PathTemplate::parse('/organizations/{organization}/users/{user}');

    expect($path->value)->toBe('/organizations/{organization}/users/{user}')
        ->and($path->parameters)->toBe(['organization', 'user'])
        ->and($path->literals())->toBe(['organizations', 'users'])
        ->and($path->parameterIndex('user'))->toBe(1)
        ->and(PathTemplate::parse($path->value)->value)->toBe($path->value);
});

it('rejects malformed paths with a distinguishable reason', function () {
    $reason = static function (string $path): string {
        try {
            PathTemplate::parse($path);
            return 'none';
        } catch (PathTemplateException $exception) {
            return $exception->reason;
        }
    };

    expect($reason('user/show'))->toBe(PathTemplateException::SYNTAX)
        ->and($reason('/user//show'))->toBe(PathTemplateException::SYNTAX)
        ->and($reason('/user/show/{Id}'))->toBe(PathTemplateException::PARAMETER_NAME)
        ->and($reason('/user/show/{id}/{id}'))->toBe(PathTemplateException::PARAMETER_DUPLICATE);
});

it('keeps PlatoPHP routing rules out of the neutral path layer', function () {
    $convention = new PlatoRouteConvention();
    $rest = PathTemplate::parse('/organizations/{organization}/users/{user}');
    $plato = PathTemplate::parse('/user/show/{id}');

    expect(array_column($convention->violations('organizations', $rest), 'code'))
        ->toBe(['route.unsupported_shape'])
        ->and($convention->action('organizations', $rest))->toBeNull()
        ->and($convention->violations('user', $plato))->toBe([])
        ->and($convention->action('user', $plato))->toBe('show')
        ->and(array_column($convention->violations('account', $plato), 'code'))
        ->toBe(['route.controller_mismatch'])
        ->and($convention->defaultPath('user', 'show', ['id'])->value)->toBe('/user/show/{id}');
});

it('takes the action and the segment order from the declared path', function () {
    [$operation, $codes] = routing_compile('/user/show/:id/:code', SegmentReq::class);
    $indexes = [];
    foreach ($operation->requestFields ?? [] as $field) {
        if ($field->source === 'segment') {
            $indexes[$field->name] = $field->segmentIndex;
        }
    }

    expect($codes)->toBe([])
        ->and($operation?->action)->toBe('show')
        ->and($operation?->path->value)->toBe('/user/show/{id}/{code}')
        ->and($indexes)->toBe(['code' => 1, 'id' => 0]);
});

it('reports stable codes when path parameters and DTO properties disagree', function () {
    expect(routing_codes('/user/show/{id}', Fixture\api\LoginReq::class))
        ->toBe(['dto.path_parameter_missing'])
        ->and(routing_codes('/user/show', StraySegmentReq::class))
        ->toBe(['dto.path_parameter_unknown'])
        ->and(routing_codes('/user/show/{id}/{id}'))
        ->toBe(['operation.path_segment_duplicate'])
        ->and(routing_codes('/user/show/{Id}'))
        ->toBe(['operation.path_parameter_name'])
        ->and(routing_codes('/users/{id}'))
        ->toBe(['route.unsupported_shape'])
        ->and(routing_codes('/account/show/{id}'))
        ->toBe(['route.controller_mismatch']);
});

it('refuses IR whose path parameters do not match its segment fields', function () {
    $build = static fn (PathTemplate $path): Operation => new Operation(
        action: 'show',
        method: 'GET',
        summary: 'Show a user',
        auth: AuthMode::None,
        requestFields: [
            new Field(name: 'id', source: 'segment', type: 'integer', required: true, segmentIndex: 0),
        ],
        responses: [new Response(200, new Schema('object'), PingResp::class)],
        path: $path,
        requestClass: user_info_req::class,
    );

    expect($build(PathTemplate::parse('/user/show/{id}'))->path->parameters)->toBe(['id']);
    expect(static fn () => $build(PathTemplate::parse('/user/show/{code}')))
        ->toThrow(InvalidArgumentException::class);
    expect(static fn () => $build(PathTemplate::parse('/user/show')))
        ->toThrow(InvalidArgumentException::class);
});
