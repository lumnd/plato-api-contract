<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

use Lumnd\PlatoApiContract\Exception\TemplateException;

/**
 * Which file backs a template name.
 *
 * Directories are searched in order and the first hit wins, so a project overrides one template by
 * dropping one file into its own directory; every other template keeps coming from the adapter's
 * built-in set.
 */
final class TemplateLocator
{
    /** @var list<string> */
    private readonly array $directories;

    /** @var array<string, string> */
    private array $resolved = [];

    private ?string $fingerprint = null;

    /**
     * @param list<string> $directories highest priority first
     * @throws TemplateException when a configured directory does not exist
     */
    public function __construct(array $directories)
    {
        $normalized = [];
        foreach ($directories as $directory) {
            if ($directory === '') {
                continue;
            }
            if (!is_dir($directory)) {
                throw TemplateException::unknownDirectory($directory);
            }
            $real = realpath($directory);
            $normalized[] = $real === false ? $directory : $real;
        }

        $this->directories = array_values(array_unique($normalized));
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        return $this->directories;
    }

    /**
     * @throws TemplateException
     */
    public function locate(string $name): string
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw TemplateException::invalidName($name);
        }

        foreach ($this->directories as $directory) {
            $file = $directory . '/' . $name . '.php';
            if (is_file($file)) {
                return $this->resolved[$name] = $file;
            }
        }

        throw TemplateException::missing($name, $this->directories);
    }

    /**
     * A hash over the templates this locator would resolve, keyed by name.
     *
     * Overriding a template, editing an override and removing one all change this value, which is
     * what makes generated artifacts detectably stale.
     */
    public function fingerprint(): string
    {
        if ($this->fingerprint !== null) {
            return $this->fingerprint;
        }

        $hashes = [];
        foreach ($this->directories as $directory) {
            foreach (glob($directory . '/*.php') ?: [] as $file) {
                $name = basename($file, '.php');
                if (isset($hashes[$name])) {
                    continue;
                }
                $hash = hash_file('sha256', $file);
                $hashes[$name] = $hash === false ? '' : $hash;
            }
        }
        ksort($hashes, SORT_STRING);

        $parts = [];
        foreach ($hashes as $name => $hash) {
            $parts[] = $name . ':' . $hash;
        }

        return $this->fingerprint = hash('sha256', implode("\0", $parts));
    }
}
