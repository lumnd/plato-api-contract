<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato\Console;

use Lumnd\PlatoApiContract\Console\CommandFailure;
use Lumnd\PlatoApiContract\Console\GenerationRunner;
use Lumnd\PlatoApiContract\Console\ProjectConfig;
use Lumnd\PlatoApiContract\Platform\Platforms;
use plato\console\command;
use plato\console\console;

/** PlatoPHP console bridge for the shared generation runner. */
final class ApiGenerateCommand implements command
{
    public static function names(): array
    {
        return ['api:generate' => 'Generate platform code and the OpenAPI document from API contracts'];
    }

    public static function usage(string $name): string
    {
        return <<<'TEXT'
--config=PATH                   Standalone options file (default: plato.config.php api_contract)
--contracts=PATH                Contract directory (defaults to <project>/api/contracts)
--output=PATH                   Project output root (defaults to the project root)
--platform=PATH                 PHP template-pack definition file
--adapter=NAME                  Platform adapter (default: plato)
--controller-namespace=NAME     Generated controller namespace
--logic-namespace=NAME          User owned Logic namespace
--controller-dir=PATH           Controller directory under the output root (default: app/control)
--logic-dir=PATH                User owned Logic directory (default: app/logic)
--templates=PATH                Directory of template overrides, searched before the built-ins
--exception=CLASS               Class refusing invalid input, implementing Runtime\Refusal
--openapi=PATH                  OpenAPI output path (default: docs/api/openapi.json)
--manifest=PATH                 Generation manifest path (default: api/manifest.json)
--base-path=PATH                Document base path prefix
--path-suffix=TEXT              Document path suffix
--title=TEXT                    OpenAPI document title
--api-version=TEXT              OpenAPI document version
--dry-run                       Verify and report without writing files
--force                         Overwrite generated files that were edited by hand

Everything but --dry-run and --force can live under api_contract in plato.config.php. An option
given here wins over the file.
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
        } catch (CommandFailure $failure) {
            foreach ($failure->messages as $message) {
                console::fail($message);
            }

            return $failure->exitCode;
        }

        $options['dry-run'] = console::option('dry-run', false) !== false;
        $options['force'] = console::option('force', false) !== false;

        return (new GenerationRunner(Platforms::resolver()))->run(
            $options,
            static function (string $line): void {
                console::success($line);
            },
            static function (string $line): void {
                console::fail($line);
            },
        );
    }
}
