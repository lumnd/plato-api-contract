<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use FilesystemIterator;
use Lumnd\PlatoApiContract\Exception\ContractValidationException;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

final class ContractLoader
{
    public function __construct(
        private readonly ContractCompiler $compiler = new ContractCompiler(),
    ) {
    }

    /**
     * A loader that compiles contracts against one platform's routing convention.
     */
    public static function forConvention(RouteConvention $convention): self
    {
        return new self(new ContractCompiler(routes: $convention));
    }

    /**
     * Discover, load, validate and normalize every PHP contract below a directory.
     *
     * @throws ContractValidationException
     */
    public function loadDirectory(string $directory): ContractCollection
    {
        $issues = [];
        $paths = $this->discover($directory, $issues);
        $files = [];

        foreach ($paths as $path) {
            try {
                $data = (static fn (string $contractFile): mixed => require $contractFile)($path);
            } catch (Throwable $exception) {
                $issues[] = new ContractIssue(
                    'contract.load_failed',
                    $path,
                    '$',
                    'Contract threw while loading: ' . $exception->getMessage(),
                );
                continue;
            }

            if (!is_array($data)) {
                $issues[] = new ContractIssue(
                    'contract.return_type',
                    $path,
                    '$',
                    'Contract files must return a PHP array.',
                );
                continue;
            }

            /** @var array<string, mixed> $data */
            $files[] = new ContractFile($path, $data);
        }

        if ($issues !== []) {
            throw new ContractValidationException($issues);
        }

        return $this->compile($files);
    }

    /**
     * Compile every file and reject names that two files claim at once.
     *
     * @param list<ContractFile> $files
     * @throws ContractValidationException
     */
    private function compile(array $files): ContractCollection
    {
        $issues = [];
        $apis = [];
        $services = [];
        $operationIds = [];

        foreach ($files as $file) {
            foreach ($this->compiler->compile($file, $issues) as $api) {
                $path = '$.services.' . $api->name;
                if (isset($services[$api->name])) {
                    $issues[] = new ContractIssue(
                        'contract.controller_duplicate',
                        $file->path,
                        $path,
                        'Service name is already defined in ' . $services[$api->name] . ': ' . $api->name,
                    );
                    continue;
                }
                $services[$api->name] = $file->path;

                foreach ($api->operations as $operation) {
                    $id = $operation->id($api->name);
                    if (isset($operationIds[$id])) {
                        $issues[] = new ContractIssue(
                            'operation.id_duplicate',
                            $file->path,
                            $path . '.' . $operation->action,
                            'operationId is already defined at ' . $operationIds[$id] . ': ' . $id,
                        );
                        continue;
                    }
                    $operationIds[$id] = $file->path . ':' . $path . '.' . $operation->action;
                }

                $apis[] = $api;
            }
        }

        if ($issues !== []) {
            throw new ContractValidationException($issues);
        }
        if ($apis === []) {
            throw new ContractValidationException([new ContractIssue(
                'contract.none_found',
                $files[0]->path ?? '',
                '$',
                'No contract declared a service.',
            )]);
        }

        return new ContractCollection($apis);
    }

    /**
     * @param list<ContractIssue> $issues
     * @return list<string>
     */
    private function discover(string $directory, array &$issues): array
    {
        $resolved = realpath($directory);
        if ($resolved === false || !is_dir($resolved)) {
            $issues[] = new ContractIssue(
                'contract.directory_missing',
                $directory,
                '$',
                'Contract directory does not exist.',
            );
            return [];
        }

        if (!is_readable($resolved)) {
            $issues[] = new ContractIssue(
                'contract.directory_unreadable',
                $resolved,
                '$',
                'Contract directory is not readable.',
            );
            return [];
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $paths[] = $file->getPathname();
        }
        sort($paths, SORT_STRING);

        if ($paths === []) {
            $issues[] = new ContractIssue(
                'contract.none_found',
                $resolved,
                '$',
                'No PHP contract files were found.',
            );
        }

        return $paths;
    }
}
