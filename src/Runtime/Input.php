<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Runtime;

use Stringable;

/**
 * Reads and coerces one declared request field, for the generated controller.
 *
 * The controller projects its request out of the values it read itself rather than out of the
 * validator's output. A validator answers whether input is acceptable; it does not answer what an
 * absent field becomes. Some validators return only fields carrying a rule or drop null values, so
 * a field with nothing to check, or with nothing sent, could otherwise never reach application code.
 * Validation still runs first and still refuses bad input; this decides what accepted input is, so
 * the declared type and the declared default are what application code sees.
 *
 * A blank string counts as absent, matching common validator behavior: an HTML form sends
 * `?nick=` for a field the user left alone.
 */
final class Input
{
    /** The value at $key, or null when it is absent, unreadable, or blank. */
    public static function at(mixed $data, string $key): mixed
    {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];

        return $value === '' ? null : $value;
    }

    /**
     * The object at $key, as something the next read can look into.
     *
     * @return array<array-key, mixed>
     */
    public static function map(mixed $data, string $key): array
    {
        $value = self::at($data, $key);

        return is_array($value) ? $value : [];
    }

    /**
     * The elements of the array at $key, renumbered so the projection is a list.
     *
     * @return list<mixed>
     */
    public static function items(mixed $data, string $key): array
    {
        return self::each(self::at($data, $key));
    }

    /**
     * The elements of a value that is already an array, for an array nested inside an array.
     *
     * @return list<mixed>
     */
    public static function each(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /** @return ($default is null ? string|null : string) */
    public static function text(mixed $value, ?string $default = ''): ?string
    {
        if ($value === null) {
            return $default;
        }

        return is_scalar($value) || $value instanceof Stringable ? (string) $value : $default;
    }

    /** @return ($default is null ? int|null : int) */
    public static function integer(mixed $value, ?int $default = 0): ?int
    {
        return $value === null ? $default : (int) self::numeric($value);
    }

    /** @return ($default is null ? float|null : float) */
    public static function decimal(mixed $value, ?float $default = 0.0): ?float
    {
        return $value === null ? $default : (float) self::numeric($value);
    }

    /**
     * A boolean the way a query string spells one: `0`, `false`, `off` and `no` are all false.
     *
     * A value that spells neither is not a false one: `fasle` is a caller's mistake, and reading it
     * as false would hand Logic a decision nobody made. Validation refuses it before this runs, so
     * what is left here is the declared default rather than a guess.
     *
     * @return ($default is null ? bool|null : bool)
     */
    public static function flag(mixed $value, ?bool $default = false): ?bool
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private static function numeric(mixed $value): int|float
    {
        return is_int($value) || is_float($value) ? $value : (float) (is_scalar($value) ? $value : 0);
    }
}
