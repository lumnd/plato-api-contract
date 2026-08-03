<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato\Console;

use Lumnd\PlatoApiContract\Console\CommandFailure;
use Lumnd\PlatoApiContract\Console\ExitCode;
use Lumnd\PlatoApiContract\Console\ProjectConfig;
use Lumnd\PlatoApiContract\Contract\ContractLoader;
use Lumnd\PlatoApiContract\Exception\ContractValidationException;
use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Platform\Platforms;
use plato\console\command;
use plato\console\console;
use Throwable;

/** PlatoPHP console bridge for contract linting. */
final class ApiLintCommand implements command
{
    public static function names(): array
    {
        return ['api:lint' => 'Validate API contract files without generating output'];
    }

    public static function usage(string $name): string
    {
        return <<<'TEXT'
--contracts=PATH    Contract directory (defaults to <project>/api/contracts)
--platform=PATH     PHP template-pack definition file
--adapter=NAME      Platform whose routing rules apply (default: plato)
--config=PATH       Standalone options file (default: plato.config.php api_contract)
TEXT;
    }

    public static function requires(): array
    {
        return [];
    }

    public static function handle(string $name): int
    {
        try {
            $options = ProjectConfig::resolvePlato(ConsoleOptions::given(), console::root());
            $directory = (string) $options['contracts'];
            $platform = $options['platform'] ?? $options['adapter'] ?? 'plato';
            $contracts = ContractLoader::forConvention(
                Platforms::resolver()->resolve($platform, $options)->routeConvention(),
            )->loadDirectory($directory);
        } catch (CommandFailure $failure) {
            foreach ($failure->messages as $message) {
                console::fail($message);
            }
            return $failure->exitCode;
        } catch (ContractValidationException $exception) {
            foreach ($exception->issues() as $issue) {
                console::fail($issue->format());
            }
            return ExitCode::CONTRACT_ERROR;
        } catch (Throwable $exception) {
            console::fail('Internal contract lint error: ' . $exception->getMessage());
            return ExitCode::INTERNAL_ERROR;
        }

        console::success(sprintf(
            'Valid: %d controller(s), %d operation(s).',
            count($contracts->apis),
            array_sum(array_map(
                static fn (ApiContract $api): int => count($api->operations),
                $contracts->apis,
            )),
        ));
        return ExitCode::SUCCESS;
    }
}
