<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Console;

use InvalidArgumentException;
use Lumnd\PlatoApiContract\Contract\ContractLoader;
use Lumnd\PlatoApiContract\Exception\ContractValidationException;
use Lumnd\PlatoApiContract\Exception\GenerationException;
use Lumnd\PlatoApiContract\Exception\TemplateException;
use Lumnd\PlatoApiContract\Generation\ArtifactStatus;
use Lumnd\PlatoApiContract\Generation\GenerationConfig;
use Lumnd\PlatoApiContract\Generation\GenerationContext;
use Lumnd\PlatoApiContract\Generation\GenerationPipeline;
use Lumnd\PlatoApiContract\Generation\GenerationResult;
use Lumnd\PlatoApiContract\Generation\OwnershipReport;
use Lumnd\PlatoApiContract\Generation\PlatformRegistry;
use Lumnd\PlatoApiContract\Generation\PlatformResolver;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Throwable;

/**
 * The shared body of `api:generate` and `api:check`, used by the standalone binary and by the
 * PlatoPHP commands.
 *
 * Contracts are loaded and fully validated first; the pipeline then verifies every artifact and
 * compares it against what the project already holds, so a failing run never leaves half a
 * generation behind and a successful one never overwrites an edit it cannot account for.
 */
final class GenerationRunner
{
    public function __construct(private readonly PlatformResolver $platforms)
    {
    }

    /**
     * @param array<string, mixed> $options as ProjectConfig::resolve() answers them
     * @param callable(string): void $out
     * @param callable(string): void $err
     */
    public function run(array $options, callable $out, callable $err): int
    {
        $force = ($options['force'] ?? false) !== false;
        $dryRun = ($options['dry-run'] ?? false) !== false;

        try {
            [$contracts, $context, $pipeline, $platform] = $this->prepare($options);

            if ($dryRun) {
                $result = $pipeline->plan($contracts, $context, $platform);

                return $this->reportPlan($result, $pipeline->inspect($result, $context), $force, $out, $err);
            }

            $result = $pipeline->run($contracts, $context, $platform, $force);
        } catch (CommandFailure $failure) {
            foreach ($failure->messages as $message) {
                $err($message);
            }
            return $failure->exitCode;
        } catch (GenerationException $exception) {
            foreach ($exception->errors() as $error) {
                $err($error);
            }
            $err('Generation failed; nothing was written.');
            return ExitCode::GENERATION_CONFLICT;
        } catch (TemplateException $exception) {
            // A broken or missing template is a configuration problem, not an internal failure.
            $err($exception->getMessage());
            $err('Generation failed; nothing was written.');
            return ExitCode::GENERATION_CONFLICT;
        } catch (InvalidArgumentException $exception) {
            $err($exception->getMessage());
            return ExitCode::INTERNAL_ERROR;
        } catch (Throwable $exception) {
            $err('Internal generation error: ' . $exception->getMessage());
            return ExitCode::INTERNAL_ERROR;
        }

        foreach ($result->written as $path) {
            $out('wrote ' . $path);
        }
        foreach ($result->removed as $path) {
            $out('removed ' . $path);
        }
        foreach ($result->report?->withStatus(ArtifactStatus::OrphanedModified) ?? [] as $state) {
            // Not a failure: no contract produces it any more, and its bytes are not ours to delete.
            $err('kept ' . $state->path . ', edited by hand and no longer generated');
        }
        $out(sprintf(
            'Wrote %d of %d artifact(s) with adapter %s; fingerprint %s.',
            count($result->written),
            count($result->artifacts),
            $result->adapter,
            substr($result->fingerprint->value(), 0, 12),
        ));
        if ($result->removed !== []) {
            $out(sprintf('Removed %d file(s) no contract produces any more.', count($result->removed)));
        }

        return ExitCode::SUCCESS;
    }

    /**
     * Report whether the project already holds what the contracts describe, without writing.
     *
     * @param array<string, mixed> $options as ProjectConfig::resolve() answers them
     * @param callable(string): void $out
     * @param callable(string): void $err
     */
    public function check(array $options, callable $out, callable $err): int
    {
        try {
            [$contracts, $context, $pipeline, $platform] = $this->prepare($options);
            $report = $pipeline->inspect($pipeline->plan($contracts, $context, $platform), $context);
        } catch (CommandFailure $failure) {
            foreach ($failure->messages as $message) {
                $err($message);
            }
            return $failure->exitCode;
        } catch (GenerationException $exception) {
            foreach ($exception->errors() as $error) {
                $err($error);
            }
            return ExitCode::GENERATION_CONFLICT;
        } catch (TemplateException $exception) {
            $err($exception->getMessage());
            return ExitCode::GENERATION_CONFLICT;
        } catch (Throwable $exception) {
            $err('Internal generation error: ' . $exception->getMessage());
            return ExitCode::INTERNAL_ERROR;
        }

        if ($report->isClean()) {
            $out(sprintf('%d generated file(s) match the contracts.', count($report->states)));

            return ExitCode::SUCCESS;
        }

        foreach ($this->differences($report) as $line) {
            $err($line);
        }
        $err(sprintf(
            '%d generated file(s) differ from the contracts; run api:generate.',
            count($report->pending()) + count($report->unaccounted()),
        ));

        return ExitCode::STALE_ARTIFACTS;
    }

    /**
     * @return list<string>
     */
    private function differences(OwnershipReport $report): array
    {
        $lines = [];
        foreach ($report->states as $state) {
            $line = match ($state->status) {
                ArtifactStatus::Create => 'missing   ' . $state->path,
                ArtifactStatus::Update => 'stale     ' . $state->path,
                ArtifactStatus::Modified => 'modified  ' . $state->path . ' (edited by hand)',
                ArtifactStatus::Orphaned => 'obsolete  ' . $state->path,
                ArtifactStatus::OrphanedModified => 'obsolete  ' . $state->path
                    . ' (edited by hand; it will be left in place)',
                default => null,
            };
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param callable(string): void $out
     * @param callable(string): void $err
     */
    private function reportPlan(
        GenerationResult $result,
        OwnershipReport $report,
        bool $force,
        callable $out,
        callable $err,
    ): int {
        foreach ($report->states as $state) {
            $line = match ($state->status) {
                ArtifactStatus::Create => 'would write ' . $state->path,
                ArtifactStatus::Update => 'would update ' . $state->path,
                ArtifactStatus::Orphaned => 'would remove ' . $state->path,
                ArtifactStatus::OrphanedModified => $force
                    ? 'would remove ' . $state->path
                    : 'would keep ' . $state->path . ', edited by hand and no longer generated',
                ArtifactStatus::Modified => $force
                    ? 'would overwrite ' . $state->path . ', edited by hand'
                    : null,
                default => null,
            };
            if ($line !== null) {
                $out($line);
            }
        }

        $edited = $force ? [] : $report->withStatus(ArtifactStatus::Modified);
        foreach ($edited as $state) {
            $err('would refuse ' . $state->path . ', edited by hand since it was generated');
        }

        $out(sprintf(
            'Planned %d artifact(s) with adapter %s; fingerprint %s.',
            count($result->artifacts),
            $result->adapter,
            substr($result->fingerprint->value(), 0, 12),
        ));

        return $edited === [] ? ExitCode::SUCCESS : ExitCode::GENERATION_CONFLICT;
    }

    /**
     * Everything both commands need: a registered adapter, validated contracts and a context.
     *
     * @param array<string, mixed> $options as ProjectConfig::resolve() answers them
     * @return array{0: ContractCollection, 1: GenerationContext, 2: GenerationPipeline, 3: string}
     * @throws CommandFailure
     */
    private function prepare(array $options): array
    {
        $contractsDirectory = (string) ($options['contracts'] ?? '');
        if ($contractsDirectory === '') {
            throw new CommandFailure(
                ExitCode::CONTRACT_ERROR,
                ['A contract directory is required (--contracts=PATH).'],
            );
        }

        $platform = $options['platform'] ?? $options['adapter'] ?? 'plato';

        try {
            $adapter = $this->platforms->resolve($platform, $options);
        } catch (TemplateException | InvalidArgumentException $exception) {
            throw new CommandFailure(
                ExitCode::GENERATION_CONFLICT,
                [$exception->getMessage(), 'Generation failed; nothing was written.'],
            );
        } catch (GenerationException $exception) {
            throw new CommandFailure(
                ExitCode::GENERATION_CONFLICT,
                [...$exception->errors(), 'Generation failed; nothing was written.'],
            );
        }

        try {
            // Contracts are compiled against the routing convention of the adapter that will
            // generate them, so lint and generation can never disagree about a path.
            $contracts = ContractLoader::forConvention($adapter->routeConvention())
                ->loadDirectory($contractsDirectory);
        } catch (ContractValidationException $exception) {
            $messages = [];
            foreach ($exception->issues() as $issue) {
                $messages[] = $issue->format();
            }
            $messages[] = sprintf(
                'Contract lint failed with %d error(s); nothing was generated.',
                count($exception->issues()),
            );

            throw new CommandFailure(ExitCode::CONTRACT_ERROR, $messages);
        } catch (Throwable $exception) {
            throw new CommandFailure(
                ExitCode::INTERNAL_ERROR,
                ['Internal contract error: ' . $exception->getMessage()],
            );
        }

        try {
            $context = new GenerationContext(
                (string) ($options['output'] ?? getcwd()),
                new GenerationConfig(
                    openApiPath: (string) ($options['openapi'] ?? 'docs/api/openapi.json'),
                    basePath: (string) ($options['base-path'] ?? ''),
                    pathSuffix: (string) ($options['path-suffix'] ?? ''),
                    title: (string) ($options['title'] ?? 'API'),
                    version: (string) ($options['api-version'] ?? '1.0.0'),
                    manifestPath: (string) ($options['manifest'] ?? 'api/manifest.json'),
                ),
            );
        } catch (InvalidArgumentException $exception) {
            throw new CommandFailure(ExitCode::INTERNAL_ERROR, [$exception->getMessage()]);
        }

        $registry = new PlatformRegistry([$adapter]);

        return [$contracts, $context, new GenerationPipeline($registry), $adapter->name()];
    }
}
