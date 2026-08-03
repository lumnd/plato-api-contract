<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Console\ExitCode;
use Lumnd\PlatoApiContract\Console\ProjectConfig;

/**
 * A project whose layout and envelope are not the defaults: everything it needs is one file, and
 * both commands read it, which is what keeps api:check from disagreeing with api:generate.
 */
function configured_project(string $case): string
{
    $root = generate_output_directory($case);
    mkdir($root . '/api', 0777, true);
    file_put_contents($root . '/' . ProjectConfig::FILE, <<<'PHP'
<?php

return [
    'api_contract' => [
        'controller-namespace' => 'Fixture\control',
        'logic-namespace' => 'Fixture\logic',
        'controller-dir' => 'api/app/control',
        'logic-dir' => 'api/app/logic',
        'openapi' => 'docs/api/openapi.json',
        'manifest' => 'api/manifest.json',
        'title' => 'Configured API',
        'exception' => Fixture\contract\refusal_exception::class,
        'strategies' => [
            'response-writer' => Fixture\contract\envelope_response_writer::class,
        ],
    ],
];
PHP);

    return $root;
}

/** @return list<string> */
function configured_arguments(string $command, string $root, string ...$extra): array
{
    return array_values([
        $command,
        contract_fixture('user'),
        $root,
        '--config=' . $root . '/' . ProjectConfig::FILE,
        ...$extra,
    ]);
}

it('writes where the configuration file says, in the envelope it names', function () {
    $root = configured_project('configured');

    $result = run_generate_cli(configured_arguments('api:generate', $root));

    expect($result['code'])->toBe(ExitCode::SUCCESS)
        ->and($result['stderr'])->toBe('')
        ->and(generated_files($root))->toBe([
            ProjectConfig::FILE,
            'api/app/control/ctl_user.php',
            'api/app/logic/user_disable_account.php',
            'api/app/logic/user_get_user_info.php',
            'api/app/logic/user_login.php',
            'api/manifest.json',
            'docs/api/openapi.json',
        ]);

    $controller = (string) file_get_contents($root . '/api/app/control/ctl_user.php');

    expect($controller)->toContain('resp::response(0, ')
        // The project registered an exception, so a refused request leaves through the failure
        // mechanism it already has instead of an envelope written into every action
        ->and($controller)->toContain('throw \Fixture\contract\refusal_exception::refuse($validator->errors());')
        ->and($controller)->not->toContain('resp::json([\'errors\'')
        ->and($controller)->not->toContain('if (plato::$auth');

    remove_directory($root);
});

it('reports nothing to do when api:check reads the same configuration file', function () {
    $root = configured_project('configured-check');
    run_generate_cli(configured_arguments('api:generate', $root));

    $result = run_generate_cli(configured_arguments('api:check', $root));

    expect($result['code'])->toBe(ExitCode::SUCCESS)
        ->and($result['stdout'])->toContain('match the contracts');

    remove_directory($root);
});

it('refuses an exception class that cannot say what a refusal answers', function () {
    $root = generate_output_directory('configured-refusal');
    mkdir($root . '/api', 0777, true);
    // A class that exists and is not a Refusal: nothing tells the generator what to call on it
    file_put_contents(
        $root . '/' . ProjectConfig::FILE,
        "<?php\n\nreturn ['exception' => 'RuntimeException'];\n",
    );

    $result = run_generate_cli(configured_arguments('api:generate', $root));

    expect($result['code'])->toBe(ExitCode::GENERATION_CONFLICT)
        ->and($result['stderr'])->toContain('does not implement')
        ->and(generated_files($root))->toBe([ProjectConfig::FILE]);

    remove_directory($root);
});

it('refuses a strategy the project named but cannot provide, before writing anything', function () {
    $root = generate_output_directory('configured-broken');
    mkdir($root . '/api', 0777, true);
    file_put_contents(
        $root . '/' . ProjectConfig::FILE,
        "<?php\n\nreturn ['strategies' => ['response-writer' => 'Project\\\\nowhere']];\n",
    );

    $result = run_generate_cli(configured_arguments('api:generate', $root));

    expect($result['code'])->toBe(ExitCode::GENERATION_CONFLICT)
        ->and($result['stderr'])->toContain('does not exist')
        ->and(generated_files($root))->toBe([ProjectConfig::FILE]);

    remove_directory($root);
});
