<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Console;

/**
 * API contract options shared by lint, generation and checking.
 *
 * `api:generate` and `api:check` must run with identical options or every artifact looks out of
 * date, and a project whose layout differs from the defaults needs a dozen of them. Repeating that
 * command line in a CI job, a Makefile and a README is how the two drift apart, so the options live
 * in one file the commands read, and the command line is left for what varies per run.
 *
 * Precedence is command line, then file, then built-in default: a one-off `--title` stays a one-off.
 */
final class ProjectConfig
{
    /** Where the standalone CLI reads project options when `--config` is not given. */
    public const FILE = 'api-contract.php';

    /** Where the PlatoPHP console bridge reads its nested api_contract options. */
    public const PLATO_FILE = 'plato.config.php';

    /**
     * Every option a configuration file may set, spelled as the command line spells it.
     *
     * `--dry-run`, `--force` and `--config` are deliberately absent: they are decisions about one
     * run, not about the project.
     */
    public const KEYS = [
        'contracts',
        'output',
        'platform',
        'adapter',
        'controller-namespace',
        'logic-namespace',
        'controller-dir',
        'logic-dir',
        'templates',
        'openapi',
        'manifest',
        'base-path',
        'path-suffix',
        'title',
        'api-version',
        'strategies',
        'exception',
    ];

    /**
     * Options naming a place on disk rather than a place inside the output root.
     *
     * These are resolved against the project root, not against an explicit configuration file's
     * own directory, so moving a standalone options file does not move its declared project paths.
     */
    private const PATH_KEYS = ['contracts', 'output', 'platform', 'templates'];

    private function __construct()
    {
    }

    /**
     * The options both generation commands run with.
     *
     * @param array<string, string|bool> $cli  options actually given on the command line
     * @param string                     $root project root, what relative paths are resolved from
     *
     * @return array<string, mixed>
     *
     * @throws CommandFailure when the file cannot be read or declares something unusable
     */
    public static function resolve(
        array $cli,
        string $root,
        string $defaultFile = self::FILE,
        bool $nestedDefault = false,
    ): array
    {
        $requested = isset($cli['config']) && $cli['config'] !== true ? (string) $cli['config'] : '';
        unset($cli['config']);

        $path = $requested !== '' ? self::absolute($requested, $root) : self::absolute($defaultFile, $root);
        $file = self::read($path, $requested !== '', $root, $requested === '' && $nestedDefault);

        $options = array_merge($file, $cli);

        // The root defaults come last so a file, and not only the command line, can move them
        $options['contracts'] ??= $root . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'contracts';
        $options['output'] ??= $root;

        return $options;
    }

    /**
     * @param array<string, string|bool> $cli
     * @return array<string, mixed>
     */
    public static function resolvePlato(array $cli, string $root): array
    {
        return self::resolve($cli, $root, self::PLATO_FILE, true);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws CommandFailure
     */
    private static function read(string $path, bool $required, string $root, bool $nested): array
    {
        if (!is_file($path)) {
            if ($required) {
                throw new CommandFailure(
                    ExitCode::GENERATION_CONFLICT,
                    ['No such configuration file: ' . $path],
                );
            }

            return [];
        }

        /** @var mixed $values */
        $values = require $path;

        if (!is_array($values)) {
            throw new CommandFailure(
                ExitCode::GENERATION_CONFLICT,
                [$path . ' must return an array of generation options.'],
            );
        }

        if ($nested || array_key_exists('api_contract', $values)) {
            $values = $values['api_contract'] ?? [];
            if (!is_array($values)) {
                throw new CommandFailure(
                    ExitCode::GENERATION_CONFLICT,
                    [$path . ': api_contract must be an array of contract options.'],
                );
            }
        }

        return self::normalize($values, $path, $root);
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     *
     * @throws CommandFailure
     */
    private static function normalize(array $values, string $path, string $root): array
    {
        $errors = [];
        $options = [];

        foreach ($values as $key => $value) {
            if (!is_string($key) || !in_array($key, self::KEYS, true)) {
                $errors[] = $path . ': unknown option ' . (is_string($key) ? $key : (string) $key)
                    . '. Known options: ' . implode(', ', self::KEYS) . '.';
                continue;
            }

            if ($key === 'strategies') {
                $strategies = self::strategies($value, $path, $errors);
                if ($strategies !== []) {
                    $options[$key] = $strategies;
                }
                continue;
            }

            if (!is_string($value) && !is_bool($value)) {
                $errors[] = $path . ': option ' . $key . ' must be a string.';
                continue;
            }

            $options[$key] = is_string($value) && in_array($key, self::PATH_KEYS, true) && $value !== ''
                ? self::absolute($value, $root)
                : $value;
        }

        if ($errors !== []) {
            throw new CommandFailure(ExitCode::GENERATION_CONFLICT, $errors);
        }

        return $options;
    }

    /**
     * @param mixed             $value
     * @param list<string>      $errors
     *
     * @return array<string, string>
     */
    private static function strategies(mixed $value, string $path, array &$errors): array
    {
        if (!is_array($value)) {
            $errors[] = $path . ': strategies must be a map of role to class name.';

            return [];
        }

        $strategies = [];
        foreach ($value as $role => $class) {
            if (!is_string($role) || !is_string($class) || $class === '') {
                $errors[] = $path . ': every strategy must be a role => class name pair.';
                continue;
            }

            $strategies[$role] = $class;
        }

        return $strategies;
    }

    /**
     * A path as given when it is already absolute, and resolved from the project root otherwise.
     */
    private static function absolute(string $path, string $root): string
    {
        if ($path === '') {
            return $path;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('#^[A-Za-z]:#', $path) === 1) {
            return $path;
        }

        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $path;
    }
}
