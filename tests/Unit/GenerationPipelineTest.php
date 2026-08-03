<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Contract\RouteConvention;
use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Generation\ArtifactVerifier;
use Lumnd\PlatoApiContract\Generation\GeneratedArtifact;
use Lumnd\PlatoApiContract\Generation\GenerationConfig;
use Lumnd\PlatoApiContract\Generation\GenerationContext;
use Lumnd\PlatoApiContract\Generation\GenerationPipeline;
use Lumnd\PlatoApiContract\Generation\Ownership;
use Lumnd\PlatoApiContract\Generation\PathGuard;
use Lumnd\PlatoApiContract\Generation\PlatformAdapter;
use Lumnd\PlatoApiContract\Generation\PlatformRegistry;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoRouteConvention;

/**
 * A platform adapter that emits exactly what a test asks for, including broken output.
 */
final class FakeAdapter implements PlatformAdapter
{
    /** @param list<GeneratedArtifact> $artifacts */
    public function __construct(
        private readonly string $name,
        private readonly array $artifacts,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function routeConvention(): RouteConvention
    {
        return new PlatoRouteConvention();
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->name);
    }

    public function templateFingerprint(): string
    {
        return '';
    }

    public function generate(ContractCollection $contracts, GenerationContext $context): array
    {
        return $this->artifacts;
    }
}

function pipeline_contracts(): ContractCollection
{
    return fixture_contracts('ping');
}

function pipeline_context(): GenerationContext
{
    return new GenerationContext(sys_get_temp_dir() . '/plato-api-pipeline-never-written');
}

/** @param list<GeneratedArtifact> $artifacts */
function pipeline_for(array $artifacts, string $name = 'fake'): GenerationPipeline
{
    return new GenerationPipeline(new PlatformRegistry([new FakeAdapter($name, $artifacts)]));
}

it('refuses artifact paths that escape the generation root', function () {
    expect(PathGuard::isSafeRelativePath('app/control/ctl_user.php'))->toBeTrue()
        ->and(PathGuard::isSafeRelativePath('../outside.php'))->toBeFalse()
        ->and(PathGuard::isSafeRelativePath('/etc/passwd'))->toBeFalse()
        ->and(PathGuard::isSafeRelativePath('app/../../escape.php'))->toBeFalse()
        ->and(PathGuard::isSafeRelativePath('C:\\Windows\\system.ini'))->toBeFalse()
        ->and(PathGuard::isSafeRelativePath('app//double.php'))->toBeFalse();
});

it('reports invalid generated PHP and JSON instead of writing it', function () {
    $errors = (new ArtifactVerifier())->verify([
        new GeneratedArtifact('app/control/broken.php', "<?php\nfinal class {\n", Ownership::Generated),
        new GeneratedArtifact('docs/api/openapi.json', '{"openapi":', Ownership::Generated),
        new GeneratedArtifact('app/control/ok.php', "<?php\n\nfinal class ok\n{\n}\n", Ownership::Generated),
    ]);

    expect($errors)->toHaveCount(2)
        ->and($errors[0])->toContain('Generated PHP is invalid in app/control/broken.php')
        ->and($errors[0])->not->toContain(sys_get_temp_dir())
        ->and($errors[1])->toContain('Generated JSON is invalid in docs/api/openapi.json');
});

it('fails the whole run when one artifact does not compile', function () {
    $pipeline = pipeline_for([
        new GeneratedArtifact('app/control/ctl_ping.php', "<?php\n\nfinal class ctl_ping\n{\n}\n", Ownership::Generated),
        new GeneratedArtifact('app/control/ctl_broken.php', "<?php\nfinal class {\n", Ownership::Generated),
    ]);

    expect(static fn () => $pipeline->run(pipeline_contracts(), pipeline_context(), 'fake'))
        ->toThrow(GenerationException::class)
        ->and(is_dir(pipeline_context()->root))->toBeFalse();
});

it('rejects two artifacts claiming the same path with different contents', function () {
    $pipeline = pipeline_for([
        new GeneratedArtifact('app/control/ctl_ping.php', "<?php\n\nfinal class a\n{\n}\n", Ownership::Generated),
        new GeneratedArtifact('app/control/ctl_ping.php', "<?php\n\nfinal class b\n{\n}\n", Ownership::Generated),
    ]);

    try {
        $pipeline->plan(pipeline_contracts(), pipeline_context(), 'fake');
        $errors = [];
    } catch (GenerationException $exception) {
        $errors = $exception->errors();
    }

    expect($errors)->toBe(['Two different artifacts claim the same path: app/control/ctl_ping.php']);
});

it('always adds the shared OpenAPI document at the configured path', function () {
    $context = new GenerationContext(
        pipeline_context()->root,
        new GenerationConfig(openApiPath: 'docs/custom/api.json', title: 'Ping', version: '2.0'),
    );
    $result = pipeline_for([])->plan(pipeline_contracts(), $context, 'fake');
    $document = json_decode($result->artifacts[0]->contents, true, flags: JSON_THROW_ON_ERROR);

    expect($result->paths())->toBe(['docs/custom/api.json', 'api/manifest.json'])
        ->and($document['info'])->toBe(['title' => 'Ping', 'version' => '2.0'])
        ->and($document['paths'])->toHaveKey('/ping/index');
});

it('changes the fingerprint when configuration changes and not otherwise', function () {
    $pipeline = pipeline_for([]);
    $first = $pipeline->plan(pipeline_contracts(), pipeline_context(), 'fake')->fingerprint;
    $second = $pipeline->plan(pipeline_contracts(), pipeline_context(), 'fake')->fingerprint;
    $third = $pipeline->plan(
        pipeline_contracts(),
        new GenerationContext(pipeline_context()->root, new GenerationConfig(basePath: '/v2')),
        'fake',
    )->fingerprint;

    expect($first->value())->toBe($second->value())
        ->and($third->value())->not->toBe($first->value())
        ->and($third->contracts)->toBe($first->contracts)
        ->and(array_keys($first->toArray()))->toBe(['adapter', 'config', 'contracts', 'templates', 'value']);
});

it('refuses an OpenAPI path that would leave the project', function () {
    expect(static fn () => new GenerationConfig(openApiPath: '../outside/openapi.json'))
        ->toThrow(InvalidArgumentException::class);
});
