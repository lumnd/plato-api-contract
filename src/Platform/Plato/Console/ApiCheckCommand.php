<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato\Console;

use Lumnd\PlatoApiContract\Console\CommandFailure;
use Lumnd\PlatoApiContract\Console\GenerationRunner;
use Lumnd\PlatoApiContract\Console\ProjectConfig;
use Lumnd\PlatoApiContract\Platform\Platforms;
use plato\console\command;
use plato\console\console;

/** PlatoPHP console bridge for checking generated artifacts. */
final class ApiCheckCommand implements command
{
    public static function names(): array
    {
        return ['api:check' => 'Report generated files that no longer match the API contracts'];
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

Exits 4 when a generated file is missing, out of date, edited by hand or no longer produced.
The options must match the ones api:generate runs with, or every artifact looks out of date --
which is what the shared configuration file is for.
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

        return (new GenerationRunner(Platforms::resolver()))->check(
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
