<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Console\ExitCode;

it('generates every artifact of a real contract set in one command', function () {
    $output = generate_output_directory('v1');
    $result = run_generate_cli(generate_arguments('user', $output));

    expect($result['code'])->toBe(ExitCode::SUCCESS)
        ->and($result['stderr'])->toBe('')
        ->and($result['stdout'])->toContain('adapter plato')
        ->and(generated_files($output))->toBe([
            'api/manifest.json',
            'app/control/ctl_user.php',
            'app/logic/user_disable_account.php',
            'app/logic/user_get_user_info.php',
            'app/logic/user_login.php',
            'docs/api/openapi.json',
        ]);

    remove_directory($output);
});

it('repeats generation without changing generated files and without touching Logic', function () {
    $output = generate_output_directory('repeat');
    run_generate_cli(generate_arguments('user', $output));

    $logic = $output . '/app/logic/user_login.php';
    file_put_contents($logic, "<?php\n// user owned implementation\n");
    $before = [];
    foreach (generated_files($output) as $path) {
        $before[$path] = (string) file_get_contents($output . '/' . $path);
    }

    $result = run_generate_cli(generate_arguments('user', $output));
    $after = [];
    foreach (generated_files($output) as $path) {
        $after[$path] = (string) file_get_contents($output . '/' . $path);
    }

    expect($result['code'])->toBe(ExitCode::SUCCESS)
        ->and($after)->toBe($before)
        ->and($after['app/logic/user_login.php'])->toBe("<?php\n// user owned implementation\n");

    remove_directory($output);
});

it('verifies without writing anything in dry run mode', function () {
    $output = generate_output_directory('dry-run');
    $result = run_generate_cli(generate_arguments('user', $output, '--dry-run'));

    expect($result['code'])->toBe(ExitCode::SUCCESS)
        ->and($result['stdout'])->toContain('would write app/control/ctl_user.php')
        ->and($result['stdout'])->toContain('Planned 6 artifact(s)')
        ->and(generated_files($output))->toBe([]);

    remove_directory($output);
});

it('writes nothing when a contract fails to lint', function () {
    $output = generate_output_directory('invalid');
    $result = run_generate_cli(generate_arguments('invalid', $output));

    expect($result['code'])->toBe(ExitCode::CONTRACT_ERROR)
        ->and($result['stderr'])->toContain('nothing was generated')
        ->and($result['stdout'])->toBe('')
        ->and(generated_files($output))->toBe([]);

    remove_directory($output);
});

it('renders one overridden template and keeps the built-ins for the rest', function () {
    $output = generate_output_directory('templates');
    $templates = generate_output_directory('templates-dir');
    file_put_contents($templates . '/logic.php', <<<'PHP'
        <?php

        /** @var \Lumnd\PlatoApiContract\Platform\Plato\View\LogicView $view */
        echo "<?php\n\nnamespace {$view->namespace};\n\n// TODO: implement {$view->class}\n";
        PHP);

    $result = run_generate_cli(generate_arguments('user', $output, '--templates=' . $templates));
    $logic = (string) file_get_contents($output . '/app/logic/user_login.php');
    $controller = (string) file_get_contents($output . '/app/control/ctl_user.php');

    expect($result['code'])->toBe(ExitCode::SUCCESS)
        ->and($logic)->toBe("<?php\n\nnamespace Fixture\\logic;\n\n// TODO: implement user_login\n")
        ->and($controller)->toContain('final class ctl_user')
        ->and($controller)->toContain('validate::make');

    remove_directory($templates);
    remove_directory($output);
});

it('refuses a template directory that does not exist, before writing files', function () {
    $output = generate_output_directory('templates-missing');
    $result = run_generate_cli(generate_arguments('user', $output, '--templates=' . $output . '/nowhere'));

    expect($result['code'])->toBe(ExitCode::GENERATION_CONFLICT)
        ->and($result['stderr'])->toContain('Template directory does not exist')
        ->and($result['stderr'])->toContain('nothing was written')
        ->and(generated_files($output))->toBe([]);

    remove_directory($output);
});

it('reports the fingerprint of the templates a run used', function () {
    $output = generate_output_directory('templates-fingerprint');
    $templates = generate_output_directory('templates-fingerprint-dir');
    copy(dirname(__DIR__, 2) . '/templates/plato/logic.php', $templates . '/logic.php');

    $plain = run_generate_cli(generate_arguments('user', $output, '--dry-run'));
    $copied = run_generate_cli(generate_arguments('user', $output, '--dry-run', '--templates=' . $templates));
    file_put_contents($templates . '/logic.php', "<?php\n\necho \"<?php\\n\";\n");
    $edited = run_generate_cli(generate_arguments('user', $output, '--dry-run', '--templates=' . $templates));

    $fingerprint = static fn (string $stdout): string => substr($stdout, (int) strpos($stdout, 'fingerprint '));

    expect($fingerprint($copied['stdout']))->toBe($fingerprint($plain['stdout']))
        ->and($fingerprint($edited['stdout']))->not->toBe($fingerprint($plain['stdout']));

    remove_directory($templates);
    remove_directory($output);
});

it('reports an unknown adapter instead of guessing one', function () {
    $output = generate_output_directory('adapter');
    $result = run_generate_cli(generate_arguments('user', $output, '--adapter=laravel'));

    expect($result['code'])->toBe(ExitCode::GENERATION_CONFLICT)
        ->and($result['stderr'])->toContain('Unknown platform adapter "laravel"')
        ->and($result['stderr'])->toContain('Available: plato')
        ->and(generated_files($output))->toBe([]);

    remove_directory($output);
});
