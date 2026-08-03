<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Contract\ContractCompiler;
use Lumnd\PlatoApiContract\Contract\ContractFile;
use Lumnd\PlatoApiContract\Generation\GeneratedArtifact;
use Lumnd\PlatoApiContract\Generation\GenerationContext;
use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\Ir\Operation;
use Lumnd\PlatoApiContract\OpenApi\DocumentOptions;
use Lumnd\PlatoApiContract\OpenApi\OpenApiGenerator;
use Lumnd\PlatoApiContract\OpenApi\OpenApiValidator;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoConfig;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoDtoHydration;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoPlatformAdapter;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoValidationRules;
use Lumnd\PlatoApiContract\Runtime\Input;
use plato\security\validate;

use function Lumnd\PlatoApiContract\Dsl\get;
use function Lumnd\PlatoApiContract\Dsl\post;
use function Lumnd\PlatoApiContract\Dsl\rules;

function fixture_config(): PlatoConfig
{
    return new PlatoConfig('Fixture\\control', 'Fixture\\logic');
}

/** @return list<GeneratedArtifact> */
function plato_artifacts(ApiContract $api): array
{
    return (new PlatoPlatformAdapter(fixture_config()))->generate(
        new ContractCollection([$api]),
        new GenerationContext('/virtual/project'),
    );
}

function generated_controller(ApiContract $api): GeneratedArtifact
{
    return plato_artifacts($api)[0];
}

/** @return list<GeneratedArtifact> */
function generated_logic(ApiContract $api): array
{
    return array_slice(plato_artifacts($api), 1);
}

it('generates a PlatoPHP controller and Logic skeleton from one contract', function () {
    $api = ping_contract();
    $controller = generated_controller($api);
    $artifacts = generated_logic($api);

    expect($controller->path)->toBe('app/control/ctl_ping.php')
        ->and($controller->contents)->toContain("'index' =>")
        ->and($controller->contents)->toContain("'GET'")
        ->and($controller->contents)->toContain('validate::make')
        ->and($controller->contents)->toContain('ping_index::handle')
        ->and(array_column($artifacts, 'path'))->toBe([
            'app/logic/ping_index.php',
        ])
        ->and($artifacts[0]->contents)->toContain('use Lumnd\\PlatoApiContract\\Runtime\\ApiContext;')
        ->and($artifacts[0]->contents)->not->toContain('implements');
});

it('never generates a DTO, because the application owns its DTO classes', function () {
    $paths = array_column(generated_logic(ping_contract()), 'path');

    expect($paths)->not->toContain('app/generated/ping_index_request.php')
        ->and($paths)->not->toContain('app/generated/ping_index_response.php');
});

/**
 * @param array<string, mixed> $services
 * @param list<Lumnd\PlatoApiContract\Contract\ContractIssue> $issues
 * @return list<Lumnd\PlatoApiContract\Ir\ApiContract>
 */
function compiled(array $services, array &$issues): array
{
    return (new ContractCompiler())->compile(
        new ContractFile('/virtual/rules.php', ['syntax' => 'v1', 'services' => $services]),
        $issues,
    );
}

it('describes a body with rules instead of a DTO class', function () {
    $issues = [];
    $apis = compiled([
        'health' => get(
            '/health/show/{id}',
            rules([
                'id' => ['required', 'integer', 'min:1', 'desc:Health check ID.'],
                'verbose' => ['boolean', 'default:false'],
                'mode' => ['nullable', 'string', 'in:brief,full'],
            ]),
            rules([
                'status' => ['string'],
                'checked_at' => ['string', 'nullable'],
            ]),
            auth: 'none',
        ),
    ], $issues);

    expect($issues)->toBe([]);
    $api = $apis[0];
    $controller = generated_controller($api)->contents;
    $logic = generated_logic($api)[0]->contents;

    expect($api->operations[0]->requestClass)->toBeNull()
        ->and($api->operations[0]->primaryResponse()->dataClass)->toBeNull()
        ->and($controller)->not->toContain('Runtime\\Dto')
        ->and($controller)->toContain('return resp::response(0, $response);')
        // Every declared field is projected, so none of them can go missing on the way to Logic.
        ->and($controller)->toContain("'id' => Input::integer(Input::at(\$input, 'id'))")
        ->and($controller)->toContain("'verbose' => Input::flag(Input::at(\$input, 'verbose'), false)")
        ->and($controller)->toContain("'mode' => Input::text(Input::at(\$input, 'mode'), null)")
        // in: is enforced, not merely documented.
        ->and($controller)->toContain("'mode' => ['scalar', 'regex_match[/^(brief|full)\$/]']")
        ->and($logic)->toContain('@param array{id: int, verbose: bool, mode: string|null} $request')
        ->and($logic)->toContain('@return array{status: string, checked_at: string|null}');
});

it('reaches a nested object and the elements of an array', function () {
    $issues = [];
    $apis = compiled([
        'order' => post(
            '/order/create',
            rules([
                'buyer.name' => ['required', 'string', 'max:50'],
                'buyer.age' => ['nullable', 'integer', 'min:0'],
                'tags' => ['nullable', 'array'],
                'tags.*' => ['string', 'max:20'],
            ]),
            rules(['id' => ['integer']]),
        ),
    ], $issues);

    expect($issues)->toBe([]);
    $controller = generated_controller($apis[0])->contents;
    $logic = generated_logic($apis[0])[0]->contents;

    expect($controller)->toContain("'buyer' => req::json('buyer')")
        // The object answers for its own container; its properties answer for themselves.
        ->and($controller)->toContain("'buyer' => ['map', 'required']")
        // The validator addresses a nested value the way an HTML form names one.
        ->and($controller)->toContain("'buyer[name]' => ['scalar', 'required', 'maxlength[50]']")
        ->and($controller)->toContain("'tags' => ['list']")
        ->and($controller)->toContain("'tags[*]' => ['scalar', 'maxlength[20]']")
        ->and($controller)->toContain("Input::at(Input::map(\$input, 'buyer'), 'name')")
        ->and($logic)->toContain('buyer: array{name: string, age: int|null}')
        ->and($logic)->toContain('tags: list<string>|null');
});

it('does not require a nullable rule-set object only because one of its children is required', function () {
    $issues = [];
    $apis = compiled([
        'order' => post(
            '/order/create',
            rules([
                'buyer' => ['nullable', 'object'],
                'buyer.name' => ['required', 'string'],
            ]),
            rules(['id' => ['integer']]),
        ),
    ], $issues);

    expect($issues)->toBe([]);
    $operation = $apis[0]->operations[0];
    $controller = generated_controller($apis[0])->contents;
    $validation = new PlatoValidationRules();
    $rules = [];
    foreach ($operation->requestFields as $field) {
        $rules = array_merge($rules, $validation->forField($field));
    }

    expect($controller)->toContain("'buyer' => ['map']")
        ->and($controller)->toContain("'buyer[name]' => ['scalar', 'required']")
        ->and(validate::make([], $rules)->errors())->toBe([])
        ->and(validate::make(['buyer' => null], $rules)->errors())->toBe([])
        ->and(array_keys(validate::make(['buyer' => []], $rules)->errors()))->toBe(['buyer[name]']);
});

it('runs the declared rules of a field inside an array element', function () {
    $issues = [];
    $apis = compiled([
        'order' => post(
            '/order/create',
            rules([
                'items' => ['required', 'array'],
                'items.*.sku' => ['required', 'string', 'max:8'],
                'items.*.qty' => ['integer', 'default:1'],
            ]),
            rules(['id' => ['integer']]),
        ),
    ], $issues);

    expect($issues)->toBe([]);
    $controller = generated_controller($apis[0])->contents;

    // `validate` cannot name a key inside an element, so the elements are named for it out of the
    // input itself: a constraint that only reaches inside an array is enforced, not just documented.
    expect($controller)->toContain("'items' => ['list', 'required']")
        // An element that is a structure is asked for the structure, the same way the body is.
        ->and($controller)->toContain("'items[*]' => ['map']")
        ->and($controller)->toContain("'items[*][sku]' => ['scalar', 'required', 'maxlength[8]']")
        ->and($controller)->toContain("'items[*][qty]' => ['scalar', 'integer']")
        ->and($controller)->toContain('use plato\\security\\validate;');
});

it('keeps the element variables of nested arrays apart', function () {
    $issues = [];
    $apis = compiled([
        'grid' => post(
            '/grid/save',
            rules(['rows' => ['required', 'array'], 'rows.*' => ['array'], 'rows.*.*' => ['integer']]),
            rules(['ok' => ['boolean']]),
        ),
    ], $issues);

    expect($issues)->toBe([]);
    $controller = generated_controller($apis[0])->contents;

    expect($controller)->toContain('static fn (mixed $item): array => array_map(static fn (mixed $item1): int')
        ->and($controller)->toContain('Input::each($item)');
});

it('keeps a regex whole when the rules are written as one pipe separated string', function () {
    $issues = [];
    $apis = compiled([
        'health' => post(
            '/health/check',
            rules([
                'mode' => 'required|string|regex:/^(brief|full)$/',
                'note' => 'nullable|string|max:10',
            ]),
            rules(['ok' => ['boolean']]),
        ),
    ], $issues);

    expect($issues)->toBe([]);
    $controller = generated_controller($apis[0])->contents;

    // A plain explode('|') would have made three rules of the pattern, two of them nonsense, and
    // refused a contract that is perfectly well formed.
    expect($apis[0]->operations[0]->requestFields[0]->pattern)->toBe('/^(brief|full)$/')
        ->and($controller)->toContain("'mode' => ['scalar', 'required', 'regex_match[/^(brief|full)\$/]']")
        ->and($controller)->toContain("'note' => ['scalar', 'maxlength[10]']");
});

it('refuses a request field whose presence is left unstated', function () {
    $issues = [];
    compiled([
        'health' => get('/health/show', rules(['note' => ['string']]), rules(['ok' => ['boolean']])),
    ], $issues);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe('rules.presence_undeclared');
});

it('refuses a required boolean, which false is indistinguishable from', function () {
    $issues = [];
    compiled([
        'health' => get('/health/show', rules(['on' => ['required', 'boolean']]), rules(['ok' => ['boolean']])),
    ], $issues);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe('rules.boolean_required');
});

it('refuses a required boolean a DTO spells as a bool without a default', function () {
    $issues = [];
    compiled([
        'user' => post('/user/create', Fixture\api\BooleanReq::class, Fixture\api\AccountResp::class),
    ], $issues);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe('dto.boolean_required')
        ->and($issues[0]->message)->toContain('Fixture\\api\\BooleanReq::$enabled');
});

it('refuses that boolean wherever a request would have to insist on it', function () {
    $issues = [];
    compiled([
        'user' => post('/user/create', Fixture\api\BooleanLinesReq::class, Fixture\api\AccountResp::class),
    ], $issues);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe('dto.boolean_required')
        ->and($issues[0]->message)->toContain('Fixture\\api\\BooleanLinesReq::$lines.*.enabled');
});

it('checks a DTO array of structures element by element and keeps a nullable one null', function () {
    $issues = [];
    $apis = compiled([
        'user' => post('/user/create', Fixture\api\BasketReq::class, Fixture\api\AccountResp::class),
    ], $issues);

    expect($issues)->toBe([]);
    $operation = $apis[0]->operations[0];
    $controller = generated_controller($apis[0])->contents;
    $validation = new PlatoValidationRules();
    $rules = [];
    foreach ($operation->requestFields as $field) {
        $rules = array_merge($rules, $validation->forField($field));
    }

    expect($controller)->toContain("'lines' => ['list', 'required']")
        ->and($controller)->toContain("'lines[*][sku]' => ['scalar', 'required', 'maxlength[8]']")
        // A property with a constructor default is not demanded of the caller.
        ->and($controller)->toContain("'lines[*][qty]' => ['scalar', 'integer', 'min[1]']")
        ->and($controller)->toContain(
            "'lines[*][gift]' => ['scalar', 'regex_match[/^(1|0|true|false|on|off|yes|no)?\$/i]']",
        )
        // The hydration reads the elements the same way, out of the same `items:` declaration:
        // one item DTO per element, so Logic is handed the type its skeleton is written with.
        ->and($controller)->toContain(
            "lines: array_map(static fn (mixed \$item): \\Fixture\\api\\BasketLineReq"
                . " => new \\Fixture\\api\\BasketLineReq(sku: Input::text(Input::at(\$item, 'sku'))",
        )
        // `?array $tags = null` says null, and the hydration says what the DTO says.
        ->and($controller)->toContain(
            "tags: (Input::at(\$input, 'tags') === null ? null : array_map("
                . "static fn (mixed \$item): string => Input::text(\$item), Input::items(\$input, 'tags')))",
        )
        ->and($controller)->toContain("'buyer' => ['map']")
        ->and($controller)->toContain("'buyer[name]' => ['scalar', 'required']")
        ->and(validate::make(['lines' => [['sku' => 'a1']]], $rules)->errors())->toBe([])
        ->and(validate::make(['lines' => [['sku' => 'a1']], 'buyer' => null], $rules)->errors())->toBe([])
        ->and(array_keys(validate::make(['lines' => [['sku' => 'a1']], 'buyer' => []], $rules)->errors()))
        ->toBe(['buyer[name]']);
});

/**
 * The request expression the hydration writes, run against real input the way a controller runs it.
 *
 * The generated source names `Input` the way the controller file imports it, and eval() carries no
 * imports of its own, so the reference is spelled out before the expression runs.
 *
 * @template T of object
 * @param class-string<T> $class
 * @param array<string, mixed> $input
 * @return T
 */
function hydrated(Operation $operation, string $class, array $input): object
{
    $expression = str_replace(
        'Input::',
        '\\' . Input::class . '::',
        (new PlatoDtoHydration())->request($operation, '$input'),
    );

    /** @var T */
    return eval('return ' . $expression . ';');
}

it('hydrates every element of a DTO array as the item the property declares', function () {
    $issues = [];
    $apis = compiled([
        'user' => post('/user/create', Fixture\api\BasketReq::class, Fixture\api\AccountResp::class),
    ], $issues);

    expect($issues)->toBe([]);
    $basket = hydrated($apis[0]->operations[0], Fixture\api\BasketReq::class, [
        'lines' => [['sku' => 'a1', 'qty' => '3'], ['sku' => 'b2']],
        'tags' => [7, 'sale'],
    ]);

    expect($basket->lines[0])->toBeInstanceOf(Fixture\api\BasketLineReq::class)
        ->and($basket->lines[0]->qty)->toBe(3)
        // An element property answers with its own constructor default, which is what the document
        // and the generated rules say it is; a plain cast of the array handed Logic no `qty` at all.
        ->and($basket->lines[1]->qty)->toBe(1)
        ->and($basket->lines[1]->gift)->toBeFalse()
        // An element scalar arrives in its declared type rather than as whatever was posted.
        ->and($basket->tags)->toBe(['7', 'sale']);
});

it('keeps a nullable DTO array null and reads an absent required one as no elements', function () {
    $issues = [];
    $apis = compiled([
        'user' => post('/user/create', Fixture\api\BasketReq::class, Fixture\api\AccountResp::class),
    ], $issues);

    expect($issues)->toBe([]);
    $basket = hydrated($apis[0]->operations[0], Fixture\api\BasketReq::class, []);

    expect($basket->tags)->toBeNull()
        ->and($basket->lines)->toBe([])
        ->and($basket->buyer)->toBeNull();
});

it('refuses a rule it does not know rather than passing it to the validator', function () {
    $issues = [];
    compiled([
        'health' => get('/health/show', rules(['note' => ['requried', 'string']]), rules(['ok' => ['boolean']])),
    ], $issues);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe('rules.unknown');
});

it('emits byte stable and structurally valid OpenAPI 3.1 JSON', function () {
    $contracts = fixture_contracts('ping');
    $generator = new OpenApiGenerator();
    $first = $generator->collectionJson($contracts, new DocumentOptions());
    $second = $generator->collectionJson($contracts, new DocumentOptions());
    $document = json_decode($first, true, flags: JSON_THROW_ON_ERROR);

    expect($first)->toBe($second)
        ->and(hash('sha256', $first))->toBe(hash('sha256', $second))
        ->and((new OpenApiValidator())->validate($document))->toBe([])
        ->and($document['paths']['/ping/index']['get']['operationId'])->toBe('ping.index')
        ->and($document['paths']['/ping/index']['get']['responses']['200']['content']['application/json']['schema']['required'])
        ->toBe(['code', 'data', 'msg']);
});

it('reuses the DTO classes of a contract and emits the default response envelope', function () {
    $contracts = fixture_contracts('user');
    $api = $contracts->api('user');

    expect($api)->not->toBeNull();
    if ($api === null) {
        return;
    }

    $controller = generated_controller($api);
    $artifacts = generated_logic($api);
    $document = (new OpenApiGenerator())->collectionDocument($contracts, new DocumentOptions());
    $operation = $document['paths']['/user/get_user_info/{id}']['get'];
    $responseSchema = $operation['responses']['200']['content']['application/json']['schema'];

    expect(array_column($artifacts, 'path'))->toBe([
        'app/logic/user_login.php',
        'app/logic/user_get_user_info.php',
        'app/logic/user_disable_account.php',
    ])
        ->and($artifacts[0]->contents)->toContain('use Lumnd\\PlatoApiContract\\Runtime\\ApiContext;')
        ->and($controller->contents)->toContain('new \\Fixture\\api\\LoginReq(')
        ->and($controller->contents)->toContain('new \\Fixture\\api\\user_info_req(')
        ->and($controller->contents)->toContain('public function get_user_info(): reply')
        ->and($controller->contents)->toContain("'id' => ['scalar', 'required', 'integer', 'min[1]']")
        // A boolean is checked for the spellings Input::flag() reads and nothing else, but it is
        // still read straight from the input rather than out of the validator, which drops every
        // null, and so it reaches the DTO whenever the caller sent it.
        ->and($controller->contents)->toContain(
            "'remember' => ['scalar', 'regex_match[/^(1|0|true|false|on|off|yes|no)?\$/i]']",
        )
        ->and($controller->contents)->toContain("remember: Input::flag(Input::at(\$input, 'remember'), null)")
        // The envelope is plato's own resp::response(), not an array written out here: the same
        // three fields, and the message left to the framework rather than frozen at generation
        // time in one language
        ->and($controller->contents)->toContain(
            "return resp::response(0, Dto::toArray(\$response, \\Fixture\\api\\LoginResp::class));",
        )
        ->and($controller->contents)->toContain('use Lumnd\\PlatoApiContract\\Runtime\\Dto;')
        ->and($operation['summary'])->toBe('Get user information')
        ->and($operation['description'])->toBe("Get the signed-in user's detailed information.")
        ->and($operation['parameters'][0]['description'])->toBe('User ID, positive integer.')
        ->and($responseSchema['required'])->toBe(['code', 'data', 'msg'])
        ->and($responseSchema['properties']['data']['properties']['email']['description'])
        ->toBe('User email address.')
        ->and($responseSchema['properties']['data']['required'])->toBe(['email', 'id', 'username']);
});
