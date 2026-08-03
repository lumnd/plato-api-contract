<?php

declare(strict_types=1);

/**
 * @return array{code: int, stdout: string, stderr: string}
 */
function run_contract_cli(string $directory): array
{
    $root = dirname(__DIR__, 2);
    $process = proc_open(
        [PHP_BINARY, $root . '/bin/plato-api', 'api:lint', $directory],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $root,
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start plato-api.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return [
        'code' => $code,
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

it('lints contracts from the standalone Composer binary and autoloads their DTO classes', function () {
    $result = run_contract_cli(dirname(__DIR__) . '/Fixtures/contracts/user');

    expect($result['code'])->toBe(0)
        ->and($result['stdout'])->toContain('Valid: 1 controller(s), 3 operation(s).')
        ->and($result['stderr'])->toBe('');
});

it('returns the contract error exit code and readable diagnostics', function () {
    $fixture = dirname(__DIR__) . '/Fixtures/contracts/invalid';
    $result = run_contract_cli($fixture);

    expect($result['code'])->toBe(2)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toContain('[operation.name_duplicate]')
        ->and($result['stderr'])->toContain('$.services.broken[1]')
        ->and($result['stderr'])->toContain('Contract lint failed with')
        ->and(is_dir($fixture . '/app'))->toBeFalse()
        ->and(is_dir($fixture . '/docs'))->toBeFalse();
});
