<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Console\ExitCode;

it('reports every artifact as missing before anything is generated', function () {
    $output = generate_output_directory('check-missing');
    $result = run_generate_cli(check_arguments('user', $output));

    expect($result['code'])->toBe(ExitCode::STALE_ARTIFACTS)
        ->and($result['stderr'])->toContain('missing   app/control/ctl_user.php')
        ->and($result['stderr'])->toContain('5 generated file(s) differ from the contracts')
        ->and(generated_files($output))->toBe([]);

    remove_directory($output);
});

it('passes on a project that was just generated, and writes nothing while checking', function () {
    $output = generate_output_directory('check-clean');
    run_generate_cli(generate_arguments('user', $output));
    $before = generated_files($output);

    $result = run_generate_cli(check_arguments('user', $output));

    expect($result['code'])->toBe(ExitCode::SUCCESS)
        ->and($result['stdout'])->toContain('5 generated file(s) match the contracts.')
        ->and($result['stderr'])->toBe('')
        ->and(generated_files($output))->toBe($before);

    remove_directory($output);
});

it('separates a file the contracts outgrew from a file somebody edited', function () {
    $output = generate_output_directory('check-drift');
    run_generate_cli(generate_arguments('user', $output));
    file_put_contents($output . '/app/control/ctl_user.php', "<?php\n// mine now\n");

    // A different document title is a configuration change: the OpenAPI file is out of date without
    // anyone having touched it.
    $result = run_generate_cli(check_arguments('user', $output, '--title=Renamed'));

    expect($result['code'])->toBe(ExitCode::STALE_ARTIFACTS)
        ->and($result['stderr'])->toContain('modified  app/control/ctl_user.php (edited by hand)')
        ->and($result['stderr'])->toContain('stale     docs/api/openapi.json');

    remove_directory($output);
});

it('reports a generated file no contract produces any more, and generation removes it', function () {
    $output = generate_output_directory('check-obsolete');
    run_generate_cli(generate_arguments('user', $output));

    $check = run_generate_cli(check_arguments('ping', $output));
    $generate = run_generate_cli(generate_arguments('ping', $output));
    $after = run_generate_cli(check_arguments('ping', $output));

    expect($check['code'])->toBe(ExitCode::STALE_ARTIFACTS)
        ->and($check['stderr'])->toContain('obsolete  app/control/ctl_user.php')
        ->and($generate['stdout'])->toContain('removed app/control/ctl_user.php')
        ->and($after['code'])->toBe(ExitCode::SUCCESS)
        ->and(generated_files($output))->not->toContain('app/control/ctl_user.php')
        ->and(generated_files($output))->toContain('app/logic/user_login.php');

    remove_directory($output);
});

it('keeps reporting an obsolete edited file after generation leaves it in place', function () {
    $output = generate_output_directory('check-obsolete-edited');
    run_generate_cli(generate_arguments('user', $output));
    file_put_contents($output . '/app/control/ctl_user.php', "<?php\n// retained legacy route\n");

    $generate = run_generate_cli(generate_arguments('ping', $output));
    $check = run_generate_cli(check_arguments('ping', $output));

    expect($generate['code'])->toBe(ExitCode::SUCCESS)
        ->and($generate['stderr'])->toContain('kept app/control/ctl_user.php, edited by hand')
        ->and($check['code'])->toBe(ExitCode::STALE_ARTIFACTS)
        ->and($check['stderr'])->toContain(
            'obsolete  app/control/ctl_user.php (edited by hand; it will be left in place)',
        )
        ->and(file_get_contents($output . '/app/control/ctl_user.php'))
        ->toBe("<?php\n// retained legacy route\n");

    remove_directory($output);
});

it('refuses to generate over an edited file and says how to resolve it', function () {
    $output = generate_output_directory('check-refuse');
    run_generate_cli(generate_arguments('user', $output));
    file_put_contents($output . '/app/control/ctl_user.php', "<?php\n// mine now\n");

    $result = run_generate_cli(generate_arguments('user', $output));

    expect($result['code'])->toBe(ExitCode::GENERATION_CONFLICT)
        ->and($result['stderr'])->toContain('Edited by hand since it was generated: app/control/ctl_user.php')
        ->and($result['stderr'])->toContain('--force')
        ->and(file_get_contents($output . '/app/control/ctl_user.php'))->toBe("<?php\n// mine now\n");

    remove_directory($output);
});

it('reports the same conflict from a dry run, without writing', function () {
    $output = generate_output_directory('check-dry-run');
    run_generate_cli(generate_arguments('user', $output));
    file_put_contents($output . '/app/control/ctl_user.php', "<?php\n// mine now\n");

    $result = run_generate_cli(generate_arguments('user', $output, '--dry-run'));

    expect($result['code'])->toBe(ExitCode::GENERATION_CONFLICT)
        ->and($result['stderr'])->toContain('would refuse app/control/ctl_user.php')
        ->and(file_get_contents($output . '/app/control/ctl_user.php'))->toBe("<?php\n// mine now\n");

    remove_directory($output);
});
