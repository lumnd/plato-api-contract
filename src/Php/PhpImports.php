<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Php;

/** Collects deterministic PHP use statements for framework templates. */
final class PhpImports
{
    /** @var array<string, true> */
    private array $imports = [];

    /** @param list<string> $imports */
    public function add(array $imports): self
    {
        foreach ($imports as $import) {
            $this->imports[ltrim($import, '\\')] = true;
        }

        return $this;
    }

    /** @return list<string> */
    public function all(): array
    {
        $imports = array_keys($this->imports);
        sort($imports, SORT_STRING);

        return $imports;
    }

    public function render(): string
    {
        return implode('', array_map(
            static fn (string $import): string => 'use ' . $import . ";\n",
            $this->all(),
        ));
    }
}
