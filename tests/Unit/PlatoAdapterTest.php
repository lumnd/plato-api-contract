<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Generation\GenerationContext;
use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Ir\AuthMode;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\Ir\Field;
use Lumnd\PlatoApiContract\Ir\Operation;
use Lumnd\PlatoApiContract\Ir\PathTemplate;
use Lumnd\PlatoApiContract\Ir\Response;
use Lumnd\PlatoApiContract\Ir\Schema;
use Lumnd\PlatoApiContract\Ir\ResponseEnvelope;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoCapabilities;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoConfig;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoControllerGenerator;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoPlatformAdapter;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoTemplates;
use Lumnd\PlatoApiContract\Platform\Plato\RequestSource;
use Lumnd\PlatoApiContract\Platform\Plato\ResponseWriter;

/**
 * A project that reads every input from one envelope instead of the framework helpers.
 */
final class PayloadRequestSource implements RequestSource
{
    public function expression(Field $field, Operation $operation): string
    {
        return "payload::value('" . $field->name . "')";
    }

    public function imports(): array
    {
        return ['app\\http\\payload'];
    }
}

/**
 * A project that answers with its own response helper.
 */
final class TextResponseWriter implements ResponseWriter
{
    public function expression(ResponseEnvelope $envelope, string $data, int $status): string
    {
        return 'answer::of(' . $data . ', ' . $status . ')';
    }

    public function returnType(): string
    {
        return 'answer';
    }

    public function imports(): array
    {
        return ['app\\http\\answer'];
    }
}

it('replaces platform strategies through constructor arguments, without subclassing', function () {
    $api = ping_contract();
    $config = new PlatoConfig('App\\control', 'App\\logic');
    $generator = new PlatoControllerGenerator(
        requests: new PayloadRequestSource(),
        responses: new TextResponseWriter(),
    );

    $contents = PlatoTemplates::renderer()->render('controller', [
        'view' => $generator->view($api, $config),
    ]);

    expect($contents)->toContain("'message' => payload::value('message')")
        ->and($contents)->toContain('use app\\http\\payload;')
        ->and($contents)->toContain('use app\\http\\answer;')
        ->and($contents)->toContain('public function index(): answer')
        ->and($contents)->toContain('return answer::of(Dto::toArray($response,')
        ->and($contents)->not->toContain('req::get')
        // The strategies are independent: validation was not replaced, so it still answers 422
        // through the framework helper.
        ->and($contents)->toContain("return resp::json(['errors' => \$validator->errors()], 422);");
});

it('lets the platform configuration decide directories, namespaces and class names', function () {
    $config = new PlatoConfig(
        controllerNamespace: 'Api\\Http',
        logicNamespace: 'Api\\Logic',
        controllerDirectory: 'src/http',
        logicDirectory: 'src/logic',
        controllerPrefix: 'controller_',
    );
    $artifacts = (new PlatoPlatformAdapter($config))->generate(
        fixture_contracts('ping'),
        new GenerationContext('/virtual/project'),
    );
    $paths = array_map(static fn ($artifact): string => $artifact->path, $artifacts);

    expect($paths)->toBe([
        'src/http/controller_ping.php',
        'src/logic/ping_index.php',
    ])
        ->and($artifacts[0]->contents)->toContain('namespace Api\\Http;')
        ->and($artifacts[0]->contents)->toContain('final class controller_ping');
});

it('refuses unsupported output paths in the platform configuration', function () {
    expect(static fn () => new PlatoConfig(controllerDirectory: '../outside'))
        ->toThrow(InvalidArgumentException::class);
});

it('names the contract and action of every capability it cannot serve', function () {
    // A contract compiled against another platform's routing convention reaches the adapter with a
    // path PlatoPHP cannot dispatch; the adapter must say so before it produces a single file.
    $contracts = new ContractCollection([new ApiContract(
        name: 'user',
        description: 'User API',
        operations: [new Operation(
            action: 'show',
            method: 'GET',
            summary: 'Show a user',
            auth: AuthMode::None,
            requestFields: [
                new Field(name: 'id', source: 'segment', type: 'integer', required: true, segmentIndex: 0),
            ],
            responses: [new Response(200, new Schema('object'), Fixture\api\PingResp::class)],
            path: PathTemplate::parse('/users/{id}'),
            requestClass: Fixture\api\user_info_req::class,
        )],
        envelope: new ResponseEnvelope('code', 0, 'msg', 'success', 'data'),
    )]);

    $errors = (new PlatoCapabilities())->errors($contracts);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toStartWith('user.show: ')
        ->and($errors[0])->toContain('PlatoPHP controller and action segments must match');

    expect(static fn () => (new PlatoPlatformAdapter())->generate(
        $contracts,
        new GenerationContext('/virtual/project'),
    ))->toThrow(GenerationException::class);
});

it('changes its fingerprint when the platform configuration changes', function () {
    $first = (new PlatoPlatformAdapter())->fingerprint();
    $second = (new PlatoPlatformAdapter())->fingerprint();
    $third = (new PlatoPlatformAdapter(new PlatoConfig(logicNamespace: 'app\\logic')))->fingerprint();

    expect($first)->toBe($second)->and($third)->not->toBe($first);
});
