<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Contract\ContractLoader;
use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Ir\ContractCollection;

uses(Tests\TestCase::class)->in('Unit', 'Feature', 'Golden');

/**
 * The directory of one contract fixture.
 */
function contract_fixture(string $name): string
{
    return __DIR__ . '/Fixtures/contracts/' . $name;
}

/**
 * Compile one contract fixture, cached because several cases compile the same files.
 */
function fixture_contracts(string $name): ContractCollection
{
    static $cache = [];

    return $cache[$name] ??= (new ContractLoader())->loadDirectory(contract_fixture($name));
}

/**
 * The single-operation ping contract: the smallest complete input a generator can receive.
 */
function ping_contract(): ApiContract
{
    $api = fixture_contracts('ping')->api('ping');
    if ($api === null) {
        throw new RuntimeException('The ping contract fixture no longer declares a ping service.');
    }

    return $api;
}

/**
 * @param list<string> $arguments
 * @return array{code: int, stdout: string, stderr: string}
 */
function run_generate_cli(array $arguments): array
{
    $root = dirname(__DIR__);
    $process = proc_open(
        [PHP_BINARY, $root . '/bin/plato-api', ...$arguments],
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

function generate_output_directory(string $name): string
{
    $path = sys_get_temp_dir() . '/plato-api-generate-' . $name . '-' . getmypid();
    remove_directory($path);
    mkdir($path, 0777, true);

    return $path;
}

function remove_directory(string $path): void
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

/** @return list<string> */
function generated_files(string $root): array
{
    if (!is_dir($root)) {
        return [];
    }

    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $paths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }
    sort($paths, SORT_STRING);

    return $paths;
}

/**
 * The arguments of one plato-api run against a contract fixture.
 *
 * @return list<string>
 */
function cli_arguments(string $command, string $fixture, string $output, string ...$extra): array
{
    return array_values([
        $command,
        __DIR__ . '/Fixtures/contracts/' . $fixture,
        $output,
        '--controller-namespace=Fixture\\control',
        '--logic-namespace=Fixture\\logic',
        ...$extra,
    ]);
}

/** @return list<string> */
function generate_arguments(string $fixture, string $output, string ...$extra): array
{
    return cli_arguments('api:generate', $fixture, $output, ...$extra);
}

/** @return list<string> */
function check_arguments(string $fixture, string $output, string ...$extra): array
{
    return cli_arguments('api:check', $fixture, $output, ...$extra);
}
