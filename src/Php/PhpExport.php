<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Php;

/**
 * An array written out as the PHP a developer would have written by hand.
 *
 * Only the keys and the scalars go through var_export(), never the assembled text. Rewriting
 * var_export()'s own `array (` layout with a string replace would rewrite the parentheses inside
 * the exported strings too, and a validation rule such as `regex_match[/^(a|b)$/]` is exactly such
 * a string.
 */
final class PhpExport
{
    private const INDENT = '    ';

    /**
     * @param array<mixed> $value
     * @param int $indent the level the closing bracket sits at, in four space steps
     */
    public static function asArray(array $value, int $indent): string
    {
        return self::export($value, $indent);
    }

    private static function export(mixed $value, int $indent): string
    {
        if (!is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        // A list of plain values reads better on one line than spread over one line per element.
        if (array_is_list($value) && self::flat($value)) {
            return '[' . implode(', ', array_map(
                static fn (mixed $item): string => var_export($item, true),
                $value,
            )) . ']';
        }

        $list = array_is_list($value);
        $inner = str_repeat(self::INDENT, $indent + 1);
        $lines = [];
        foreach ($value as $key => $item) {
            $lines[] = $inner
                . ($list ? '' : var_export($key, true) . ' => ')
                . self::export($item, $indent + 1)
                . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat(self::INDENT, $indent) . ']';
    }

    /** @param array<mixed> $value */
    private static function flat(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item)) {
                return false;
            }
        }

        return true;
    }
}
