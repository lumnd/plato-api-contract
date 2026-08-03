<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Platform\Plato;

use InvalidArgumentException;
use Lumnd\PlatoApiContract\Generation\PathGuard;
use Lumnd\PlatoApiContract\Ir\ApiContract;
use Lumnd\PlatoApiContract\Ir\Operation;

/**
 * Where the PlatoPHP adapter writes and how it names what it writes.
 *
 * Output layout and naming are adapter concerns: another platform has different directories, class
 * name shapes and namespaces, and must not have to widen a shared configuration object to say so.
 */
final readonly class PlatoConfig
{
    public function __construct(
        public string $controllerNamespace = 'control',
        public string $logicNamespace = 'logic',
        public string $controllerDirectory = 'app/control',
        public string $logicDirectory = 'app/logic',
        public string $controllerPrefix = 'ctl_',
        public ?string $templateDirectory = null,
        public ?string $exception = null,
    ) {
        foreach ([$controllerNamespace, $logicNamespace] as $namespace) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace) !== 1) {
                throw new InvalidArgumentException('Invalid PHP namespace: ' . $namespace);
            }
        }

        foreach ([$controllerDirectory, $logicDirectory] as $directory) {
            if (!PathGuard::isSafeRelativePath($directory)) {
                throw new InvalidArgumentException(
                    'Output directories must be relative paths inside the project: ' . $directory,
                );
            }
        }

        if ($controllerPrefix !== '' && preg_match('/^[a-z_][a-z0-9_]*$/', $controllerPrefix) !== 1) {
            throw new InvalidArgumentException('Invalid controller class prefix: ' . $controllerPrefix);
        }

        if ($templateDirectory === '') {
            throw new InvalidArgumentException(
                'The template override directory must be a path, or null to use the built-in templates.',
            );
        }

        // Only the spelling here; whether the class can serve is asked once the project's autoloader
        // is in reach, by PlatoRefusal, so that a typo is reported as configuration rather than as a
        // fatal inside a template
        if ($exception !== null && preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $exception) !== 1) {
            throw new InvalidArgumentException('Invalid exception class name: ' . $exception);
        }
    }

    /**
     * The shared symbol prefix of one operation: `user_login`.
     */
    public function symbol(ApiContract $api, Operation $operation): string
    {
        return $api->name . '_' . $operation->action;
    }

    public function controllerClass(ApiContract $api): string
    {
        return $this->controllerPrefix . $api->name;
    }

    public function controllerPath(ApiContract $api): string
    {
        return $this->controllerDirectory . '/' . $this->controllerClass($api) . '.php';
    }

    public function logicPath(string $class): string
    {
        return $this->logicDirectory . '/' . $class . '.php';
    }

    public function logicFqn(string $class): string
    {
        return '\\' . $this->logicNamespace . '\\' . $class;
    }

    public function hash(): string
    {
        return hash('sha256', implode("\0", [
            $this->controllerNamespace,
            $this->logicNamespace,
            $this->controllerDirectory,
            $this->logicDirectory,
            $this->controllerPrefix,
            // What a refused request answers is written into every action, so registering a class
            // or dropping it puts the generated controllers out of date
            (string) $this->exception,
        ]));
    }

    /**
     * The registered class as a generated file names it, absolute, or null when none is registered.
     */
    public function exceptionFqn(): ?string
    {
        return $this->exception === null ? null : '\\' . ltrim($this->exception, '\\');
    }
}
