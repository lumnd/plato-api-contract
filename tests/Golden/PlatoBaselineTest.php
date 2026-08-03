<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Generation\GenerationConfig;
use Lumnd\PlatoApiContract\Generation\GenerationContext;
use Lumnd\PlatoApiContract\Generation\GenerationPipeline;
use Lumnd\PlatoApiContract\Generation\PlatformRegistry;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoConfig;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoPlatformAdapter;
use Tests\Golden\GoldenFiles;

function golden_config(): PlatoConfig
{
    return new PlatoConfig('App\\control', 'App\\logic');
}

function golden_pipeline(): GenerationPipeline
{
    return new GenerationPipeline(new PlatformRegistry([new PlatoPlatformAdapter(golden_config())]));
}

/**
 * Everything a project would receive, produced through the real pipeline.
 *
 * @return array<string, string> path to contents
 */
function golden_artifacts(ContractCollection $contracts): array
{
    $context = new GenerationContext(
        '/virtual/project',
        new GenerationConfig(title: 'Golden API', version: '1.0.0'),
    );

    return GoldenFiles::index(
        golden_pipeline()->plan($contracts, $context, PlatoPlatformAdapter::NAME)->artifacts,
    );
}

function golden_compare(string $case, ContractCollection $contracts): void
{
    $files = golden_artifacts($contracts);
    if (GoldenFiles::updating()) {
        GoldenFiles::write($case, $files);
    }

    expect(GoldenFiles::stored($case))->toBe(array_keys($files));
    foreach ($files as $path => $contents) {
        expect(GoldenFiles::read($case, $path))->toBe($contents);
    }
}

it('freezes the PlatoPHP output of the single operation ping contract', function () {
    golden_compare('ping', fixture_contracts('ping'));
});

it('freezes the PlatoPHP output of the user contract with mixed authentication requirements', function () {
    golden_compare('user', fixture_contracts('user'));
});

it('keeps every frozen PHP artifact syntactically valid', function () {
    $files = [];
    foreach (['ping', 'user'] as $case) {
        foreach (GoldenFiles::stored($case) as $path) {
            if (str_ends_with($path, '.php')) {
                $files[] = GoldenFiles::directory($case) . '/' . $path;
            }
        }
    }

    expect($files)->not->toBeEmpty();
    foreach ($files as $file) {
        $output = [];
        $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        expect($status)->toBe(0, implode("\n", $output));
    }
});

it('produces byte identical output when generation runs twice', function () {
    $contracts = fixture_contracts('user');

    expect(golden_artifacts($contracts))->toBe(golden_artifacts($contracts));
});
