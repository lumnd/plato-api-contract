<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Generation\ArtifactStatus;
use Lumnd\PlatoApiContract\Generation\ArtifactWriter;
use Lumnd\PlatoApiContract\Generation\GeneratedArtifact;
use Lumnd\PlatoApiContract\Generation\GenerationConfig;
use Lumnd\PlatoApiContract\Generation\GenerationContext;
use Lumnd\PlatoApiContract\Generation\GenerationFingerprint;
use Lumnd\PlatoApiContract\Generation\GenerationPipeline;
use Lumnd\PlatoApiContract\Generation\Manifest;
use Lumnd\PlatoApiContract\Generation\Ownership;
use Lumnd\PlatoApiContract\Generation\OwnershipInspector;
use Lumnd\PlatoApiContract\Generation\PlatformRegistry;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoConfig;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoPlatformAdapter;

function ownership_root(string $name): string
{
    $path = sys_get_temp_dir() . '/plato-api-ownership-' . $name . '-' . getmypid();
    ownership_delete($path);
    mkdir($path, 0777, true);

    return $path;
}

function ownership_delete(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function ownership_pipeline(): GenerationPipeline
{
    return new GenerationPipeline(new PlatformRegistry([
        new PlatoPlatformAdapter(new PlatoConfig('Fixture\\control', 'Fixture\\logic')),
    ]));
}

function ownership_context(string $root): GenerationContext
{
    return new GenerationContext($root, new GenerationConfig());
}

/**
 * Generate into a fresh project, the way a first run leaves it.
 */
function ownership_generate(string $root, ?ContractCollection $contracts = null, bool $force = false): void
{
    ownership_pipeline()->run(
        $contracts ?? fixture_contracts('ping'),
        ownership_context($root),
        PlatoPlatformAdapter::NAME,
        $force,
    );
}

function ownership_report(string $root, ?ContractCollection $contracts = null): Lumnd\PlatoApiContract\Generation\OwnershipReport
{
    $pipeline = ownership_pipeline();
    $context = ownership_context($root);

    return $pipeline->inspect(
        $pipeline->plan($contracts ?? fixture_contracts('ping'), $context, PlatoPlatformAdapter::NAME),
        $context,
    );
}

it('records a hash for every file it owns and none for the Logic it hands over', function () {
    $root = ownership_root('record');
    ownership_generate($root);
    $manifest = Manifest::fromFile($root . '/api/manifest.json');

    $controller = $manifest->entry('app/control/ctl_ping.php');
    $logic = $manifest->entry('app/logic/ping_index.php');

    expect($manifest->adapter)->toBe('plato')
        ->and($controller?->ownership)->toBe(Ownership::Generated)
        ->and($controller?->sha256)->toBe(hash('sha256', (string) file_get_contents($root . '/app/control/ctl_ping.php')))
        ->and($logic?->ownership)->toBe(Ownership::User)
        ->and($logic?->sha256)->toBeNull()
        ->and($manifest->entry('api/manifest.json'))->toBeNull();

    ownership_delete($root);
});

it('finds nothing to do when the project already holds what the contracts describe', function () {
    $root = ownership_root('clean');
    ownership_generate($root);
    $report = ownership_report($root);

    expect($report->isClean())->toBeTrue()
        ->and($report->status('app/control/ctl_ping.php'))->toBe(ArtifactStatus::Current)
        ->and($report->status('app/logic/ping_index.php'))->toBe(ArtifactStatus::Kept);

    ownership_delete($root);
});

it('adopts a project whose generated output is committed and up to date, without a manifest', function () {
    $root = ownership_root('adopt');
    ownership_generate($root);
    unlink($root . '/api/manifest.json');

    // Nothing recorded, yet every byte on disk is a byte this run would write: there is no edit to
    // protect, so a project that adopts the manifest is adopted in silence.
    expect(ownership_report($root)->isClean())->toBeTrue();

    ownership_delete($root);
});

it('reports a generated file that was edited by hand', function () {
    $root = ownership_root('edited');
    ownership_generate($root);
    file_put_contents($root . '/app/control/ctl_ping.php', "<?php\n// a hand written route\n");

    $report = ownership_report($root);

    expect($report->status('app/control/ctl_ping.php'))->toBe(ArtifactStatus::Modified)
        ->and($report->isClean())->toBeFalse()
        ->and($report->unaccounted())->toHaveCount(1);

    ownership_delete($root);
});

it('separates an out of date file from an edited one', function () {
    $root = ownership_root('stale');
    ownership_generate($root);

    // The same bytes the last run recorded, produced by a run that now wants different ones.
    $pipeline = ownership_pipeline();
    $context = new GenerationContext($root, new GenerationConfig(title: 'A different title'));
    $report = $pipeline->inspect(
        $pipeline->plan(fixture_contracts('ping'), $context, PlatoPlatformAdapter::NAME),
        $context,
    );

    expect($report->status('docs/api/openapi.json'))->toBe(ArtifactStatus::Update)
        ->and($report->unaccounted())->toBe([]);

    ownership_delete($root);
});

it('refuses to write over an edit, and writes nothing at all when it refuses', function () {
    $root = ownership_root('refuse');
    ownership_generate($root);
    file_put_contents($root . '/app/control/ctl_ping.php', "<?php\n// mine now\n");
    $document = (string) file_get_contents($root . '/docs/api/openapi.json');
    file_put_contents($root . '/docs/api/openapi.json', '{}');

    $errors = [];
    try {
        ownership_generate($root);
    } catch (GenerationException $exception) {
        $errors = $exception->errors();
    }

    expect($errors)->toHaveCount(3)
        ->and($errors[0])->toBe('Edited by hand since it was generated: app/control/ctl_ping.php')
        ->and($errors[2])->toContain('--force')
        ->and(file_get_contents($root . '/app/control/ctl_ping.php'))->toBe("<?php\n// mine now\n")
        ->and(file_get_contents($root . '/docs/api/openapi.json'))->not->toBe($document);

    ownership_delete($root);
});

it('overwrites an edited file only when told to force it', function () {
    $root = ownership_root('force');
    ownership_generate($root);
    $generated = (string) file_get_contents($root . '/app/control/ctl_ping.php');
    file_put_contents($root . '/app/control/ctl_ping.php', "<?php\n// mine now\n");

    ownership_generate($root, force: true);

    expect(file_get_contents($root . '/app/control/ctl_ping.php'))->toBe($generated)
        ->and(ownership_report($root)->isClean())->toBeTrue();

    ownership_delete($root);
});

it('never reads, replaces or reports the Logic file an application owns', function () {
    $root = ownership_root('logic');
    ownership_generate($root);
    file_put_contents($root . '/app/logic/ping_index.php', "<?php\n// the real implementation\n");

    $report = ownership_report($root);
    ownership_generate($root);

    expect($report->status('app/logic/ping_index.php'))->toBe(ArtifactStatus::Kept)
        ->and($report->isClean())->toBeTrue()
        ->and(file_get_contents($root . '/app/logic/ping_index.php'))
        ->toBe("<?php\n// the real implementation\n");

    ownership_delete($root);
});

it('removes a controller whose contract is gone, so no route outlives its declaration', function () {
    $root = ownership_root('orphan');
    ownership_generate($root, fixture_contracts('user'));

    expect(is_file($root . '/app/control/ctl_user.php'))->toBeTrue();

    $report = ownership_report($root, fixture_contracts('ping'));
    ownership_generate($root, fixture_contracts('ping'));

    expect($report->status('app/control/ctl_user.php'))->toBe(ArtifactStatus::Orphaned)
        ->and(is_file($root . '/app/control/ctl_user.php'))->toBeFalse()
        ->and(is_file($root . '/app/logic/user_login.php'))->toBeTrue()
        ->and(Manifest::fromFile($root . '/api/manifest.json')->entry('app/control/ctl_user.php'))->toBeNull();

    ownership_delete($root);
});

it('leaves an obsolete file alone when it was edited by hand', function () {
    $root = ownership_root('orphan-edited');
    ownership_generate($root, fixture_contracts('user'));
    file_put_contents($root . '/app/control/ctl_user.php', "<?php\n// kept for the legacy client\n");

    $report = ownership_report($root, fixture_contracts('ping'));
    ownership_generate($root, fixture_contracts('ping'));
    $after = ownership_report($root, fixture_contracts('ping'));
    $manifest = Manifest::fromFile($root . '/api/manifest.json');

    expect($report->status('app/control/ctl_user.php'))->toBe(ArtifactStatus::OrphanedModified)
        ->and(file_get_contents($root . '/app/control/ctl_user.php'))
        ->toBe("<?php\n// kept for the legacy client\n")
        ->and($after->status('app/control/ctl_user.php'))->toBe(ArtifactStatus::OrphanedModified)
        ->and($after->isClean())->toBeFalse()
        ->and($manifest->entry('app/control/ctl_user.php'))->not->toBeNull();

    ownership_generate($root, fixture_contracts('ping'), force: true);

    expect(is_file($root . '/app/control/ctl_user.php'))->toBeFalse()
        ->and(ownership_report($root, fixture_contracts('ping'))->isClean())->toBeTrue()
        ->and(Manifest::fromFile($root . '/api/manifest.json')->entry('app/control/ctl_user.php'))->toBeNull();

    ownership_delete($root);
});

it('does not publish output or a new manifest when obsolete cleanup fails', function () {
    $root = ownership_root('cleanup-failure');
    ownership_generate($root, fixture_contracts('user'));
    $manifest = (string) file_get_contents($root . '/api/manifest.json');
    $controlDirectory = $root . '/app/control';
    chmod($controlDirectory, 0555);
    if (is_writable($controlDirectory)) {
        chmod($controlDirectory, 0777);
        $this->markTestSkipped('The current user can write through directory permissions.');
    }

    $failed = false;
    try {
        ownership_generate($root, fixture_contracts('ping'));
    } catch (Throwable) {
        $failed = true;
    } finally {
        chmod($controlDirectory, 0777);
    }

    expect($failed)->toBeTrue()
        ->and(file_get_contents($root . '/api/manifest.json'))->toBe($manifest)
        ->and(is_file($root . '/app/control/ctl_ping.php'))->toBeFalse()
        ->and(is_file($root . '/app/logic/ping_index.php'))->toBeFalse();

    ownership_delete($root);
});

it('blames a corrupt manifest on the manifest, not on the project', function () {
    $root = ownership_root('corrupt');
    ownership_generate($root);
    file_put_contents($root . '/api/manifest.json', '{ not json');

    expect(static fn () => ownership_report($root))
        ->toThrow(GenerationException::class, 'The generation manifest is not valid JSON');

    ownership_delete($root);
});

it('rejects an artifact path from a manifest that could leave the project', function () {
    $root = ownership_root('unsafe-manifest');
    $path = $root . '/manifest.json';
    file_put_contents($path, json_encode([
        'manifest_version' => Manifest::VERSION,
        'adapter' => 'plato',
        'fingerprint' => [],
        'artifacts' => [
            '../outside.php' => [
                'ownership' => Ownership::Generated->value,
                'sha256' => hash('sha256', 'outside'),
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    expect(static fn () => Manifest::fromFile($path))
        ->toThrow(GenerationException::class, 'unsafe artifact path: ../outside.php');

    ownership_delete($root);
});

it('leaves a file that already holds the planned bytes untouched', function () {
    $root = ownership_root('untouched');
    $artifact = new GeneratedArtifact('app/control/ctl_ping.php', "<?php\n", Ownership::Generated);
    $writer = new ArtifactWriter();

    expect($writer->write($root, [$artifact]))->toBe(['app/control/ctl_ping.php'])
        ->and($writer->write($root, [$artifact]))->toBe([]);

    ownership_delete($root);
});

it('round trips through JSON and sorts its entries by path', function () {
    $manifest = Manifest::fromArtifacts(
        'plato',
        new GenerationFingerprint('c', 'g', 'a', 't'),
        [
            new GeneratedArtifact('docs/api/openapi.json', '{}', Ownership::Generated),
            new GeneratedArtifact('app/logic/ping_index.php', '<?php', Ownership::User),
            new GeneratedArtifact('api/manifest.json', 'ignored', Ownership::Tool),
        ],
    );

    $path = ownership_root('json') . '/manifest.json';
    file_put_contents($path, $manifest->toJson());
    $reloaded = Manifest::fromFile($path);

    expect(array_keys($reloaded->entries))->toBe(['app/logic/ping_index.php', 'docs/api/openapi.json'])
        ->and($reloaded->fingerprint->value())->toBe($manifest->fingerprint->value())
        ->and($reloaded->entry('docs/api/openapi.json')?->sha256)->toBe(hash('sha256', '{}'))
        ->and(Manifest::fromFile(dirname($path) . '/absent.json')->isEmpty())->toBeTrue();

    ownership_delete(dirname($path));
});

it('ignores the manifest itself when it decides what to protect', function () {
    $report = (new OwnershipInspector())->inspect(
        sys_get_temp_dir(),
        Manifest::empty(),
        [new GeneratedArtifact('api/manifest.json', '{}', Ownership::Tool)],
    );

    expect($report->states)->toBe([])
        ->and($report->isClean())->toBeTrue();
});
