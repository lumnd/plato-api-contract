<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Console\CommandFailure;
use Lumnd\PlatoApiContract\Console\ExitCode;
use Lumnd\PlatoApiContract\Console\ProjectConfig;
use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoStrategies;

function config_root(string $case): string
{
    $path = sys_get_temp_dir() . '/plato-api-config-' . $case . '-' . getmypid();
    remove_directory($path);
    mkdir($path . '/api', 0777, true);

    return $path;
}

function write_config(string $root, string $body): string
{
    $path = $root . '/' . ProjectConfig::FILE;
    file_put_contents($path, "<?php\n\nreturn ['api_contract' => " . $body . "];\n");

    return $path;
}

it('answers with the root defaults when the project keeps no configuration file', function () {
    $root = config_root('absent');

    $options = ProjectConfig::resolve([], $root);

    expect($options['contracts'])->toBe($root . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'contracts')
        ->and($options['output'])->toBe($root);

    remove_directory($root);
});

it('reads the options a project keeps in its configuration file', function () {
    $root = config_root('file');
    write_config($root, "[
        'contracts' => 'api/contracts',
        'controller-dir' => 'api/app/control',
        'openapi' => 'docs/api/openapi.json',
        'title' => 'Project API',
    ]");

    $options = ProjectConfig::resolve([], $root);

    expect($options['contracts'])->toBe($root . DIRECTORY_SEPARATOR . 'api/contracts')
        ->and($options['controller-dir'])->toBe('api/app/control')
        ->and($options['openapi'])->toBe('docs/api/openapi.json')
        ->and($options['title'])->toBe('Project API');

    remove_directory($root);
});

it('lets the command line win over the file, so a one-off stays a one-off', function () {
    $root = config_root('precedence');
    write_config($root, "['title' => 'Project API', 'api-version' => '2.0.0']");

    $options = ProjectConfig::resolve(['title' => 'Just this run'], $root);

    expect($options['title'])->toBe('Just this run')
        ->and($options['api-version'])->toBe('2.0.0');

    remove_directory($root);
});

it('leaves an absolute path in the file alone', function () {
    $root = config_root('absolute');
    write_config($root, "['contracts' => '/srv/contracts']");

    expect(ProjectConfig::resolve([], $root)['contracts'])->toBe('/srv/contracts');

    remove_directory($root);
});

it('refuses an option no command understands, and says which ones it does', function () {
    $root = config_root('unknown');
    write_config($root, "['controller_dir' => 'api/app/control']");

    $failure = null;
    try {
        ProjectConfig::resolve([], $root);
    } catch (CommandFailure $exception) {
        $failure = $exception;
    }

    expect($failure)->not->toBeNull()
        ->and($failure?->exitCode)->toBe(ExitCode::GENERATION_CONFLICT)
        ->and(implode("\n", $failure === null ? [] : $failure->messages))->toContain('unknown option controller_dir')
        ->and(implode("\n", $failure === null ? [] : $failure->messages))->toContain('controller-dir');

    remove_directory($root);
});

it('refuses a configuration file that was asked for by name and is not there', function () {
    $root = config_root('missing');

    $failure = null;
    try {
        ProjectConfig::resolve(['config' => 'api/nowhere.php'], $root);
    } catch (CommandFailure $exception) {
        $failure = $exception;
    }

    expect($failure)->not->toBeNull()
        ->and($failure?->exitCode)->toBe(ExitCode::GENERATION_CONFLICT)
        ->and(implode("\n", $failure === null ? [] : $failure->messages))->toContain('No such configuration file');

    remove_directory($root);
});

it('refuses a configuration file that does not return an array', function () {
    $root = config_root('not-array');
    file_put_contents($root . '/' . ProjectConfig::FILE, "<?php\n\nreturn 'nonsense';\n");

    expect(static fn () => ProjectConfig::resolve([], $root))
        ->toThrow(CommandFailure::class);

    remove_directory($root);
});

it('accepts a standalone options file at an arbitrary explicit path', function () {
    $root = config_root('explicit');
    $path = $root . '/config/contracts.php';
    mkdir(dirname($path), 0777, true);
    file_put_contents($path, "<?php\n\nreturn ['title' => 'Standalone API'];\n");

    expect(ProjectConfig::resolve(['config' => $path], $root)['title'])->toBe('Standalone API');

    remove_directory($root);
});

it('resolves a template-pack platform file from the project root', function () {
    $root = config_root('platform');
    write_config($root, "['platform' => 'api/platform.php']");

    expect(ProjectConfig::resolve([], $root)['platform'])->toBe($root . DIRECTORY_SEPARATOR . 'api/platform.php');

    remove_directory($root);
});

it('carries the strategy map through to the generator', function () {
    $root = config_root('strategies');
    write_config($root, "['strategies' => ['response-writer' => 'Fixture\\\\contract\\\\envelope_response_writer']]");

    $options = ProjectConfig::resolve([], $root);

    expect($options['strategies'])->toBe([
        'response-writer' => 'Fixture\\contract\\envelope_response_writer',
    ]);

    remove_directory($root);
});

it('builds the adapter generator from the strategies a project named', function () {
    $default = PlatoStrategies::generator([]);
    $replaced = PlatoStrategies::generator([
        'response-writer' => Fixture\contract\envelope_response_writer::class,
    ]);

    expect($replaced->fingerprint())->not->toBe($default->fingerprint());
});

it('refuses a strategy that cannot serve its role, naming what is wrong with it', function (
    array $strategies,
    string $expected,
) {
    $errors = [];
    try {
        PlatoStrategies::generator($strategies);
    } catch (GenerationException $exception) {
        $errors = $exception->errors();
    }

    expect(implode("\n", $errors))->toContain($expected);
})->with([
    'unknown role' => [['response_writer' => 'Fixture\\contract\\envelope_response_writer'], 'Unknown strategy role'],
    'missing class' => [['response-writer' => 'Fixture\\contract\\nowhere'], 'does not exist'],
    'wrong interface' => [['response-writer' => 'Fixture\\api\\PingReq'], 'does not implement'],
    'needs arguments' => [['response-writer' => 'Fixture\\contract\\demanding_response_writer'], 'without arguments'],
]);
