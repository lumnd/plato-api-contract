<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use Lumnd\PlatoApiContract\Ir\Field;

/**
 * One field's rule list, read.
 *
 * The vocabulary is closed on purpose: an unknown rule is a contract error rather than something
 * quietly handed to the validator, because a typo in `requried` would otherwise pass lint and then
 * refuse every request at runtime.
 */
final readonly class FieldRules
{
    public const PRESENCE = ['required', 'nullable', 'optional'];

    /** Rule name to the IR type it declares. */
    public const TYPES = [
        'string' => 'string',
        'integer' => 'integer',
        'numeric' => 'number',
        'boolean' => 'boolean',
        'array' => 'array',
        'object' => 'object',
    ];

    /**
     * @param list<string|int|float|bool|null> $enum
     * @param string|null $pattern a PCRE, delimiters included, exactly as the contract wrote it
     */
    private function __construct(
        public string $type,
        public bool $typeDeclared,
        public ?string $presence,
        public mixed $default,
        public bool $hasDefault,
        public ?string $source,
        public string $description,
        public ?string $format,
        public ?int $minLength,
        public ?int $maxLength,
        public int|float|null $minimum,
        public int|float|null $maximum,
        public ?string $pattern,
        public array $enum,
    ) {
    }

    /**
     * @param list<string> $rules
     *
     * @throws SchemaException when a rule is unknown, malformed, or contradicts another
     */
    public static function parse(string $path, array $rules): self
    {
        [$type, $typeDeclared] = self::type($path, $rules);

        $presence = null;
        $default = null;
        $hasDefault = false;
        $source = null;
        $description = '';
        $format = null;
        $minLength = null;
        $maxLength = null;
        $minimum = null;
        $maximum = null;
        $pattern = null;
        $enum = [];

        foreach ($rules as $rule) {
            [$name, $argument] = self::split($rule);

            if (in_array($name, self::PRESENCE, true)) {
                if ($presence !== null && $presence !== $name) {
                    throw self::fail('rules.presence_conflict', $path . ' declares both ' . $presence . ' and ' . $name . '.');
                }
                $presence = $name;
                continue;
            }

            if (isset(self::TYPES[$name])) {
                continue;
            }

            switch ($name) {
                case 'min':
                    [$minLength, $minimum] = self::bound($path, $type, $name, $argument, $minLength, $minimum);
                    break;
                case 'max':
                    [$maxLength, $maximum] = self::bound($path, $type, $name, $argument, $maxLength, $maximum);
                    break;
                case 'size':
                    if ($type !== 'string') {
                        throw self::fail('rules.bound_type', 'size: only applies to a string field, not ' . $type . ': ' . $path);
                    }
                    $minLength = $maxLength = self::count($path, 'size', $argument);
                    break;
                case 'between':
                    $parts = explode(',', (string) $argument);
                    if (count($parts) !== 2) {
                        throw self::fail('rules.malformed', 'between: takes two values, as between:1,10: ' . $path);
                    }
                    [$minLength, $minimum] = self::bound($path, $type, 'min', trim($parts[0]), $minLength, $minimum);
                    [$maxLength, $maximum] = self::bound($path, $type, 'max', trim($parts[1]), $maxLength, $maximum);
                    break;
                case 'regex':
                    $pattern = self::pattern($path, $argument);
                    break;
                case 'email':
                    $format = 'email';
                    break;
                case 'url':
                    $format = 'uri';
                    break;
                case 'date':
                    $format = 'date';
                    break;
                case 'in':
                    $enum = self::enum($path, $type, $argument);
                    break;
                case 'default':
                    $default = self::literal($path, $type, $argument);
                    $hasDefault = true;
                    break;
                case 'desc':
                    $description = (string) $argument;
                    break;
                case 'from':
                    $source = self::source($path, $argument);
                    break;
                default:
                    throw self::fail('rules.unknown', 'Unknown rule "' . $name . '" on ' . $path . '.');
            }
        }

        if ($hasDefault && $presence !== null) {
            throw self::fail(
                'rules.presence_conflict',
                $path . ' declares default: together with ' . $presence . '; a default already makes it optional.',
            );
        }

        return new self(
            type: $type,
            typeDeclared: $typeDeclared,
            presence: $presence,
            default: $default,
            hasDefault: $hasDefault,
            source: $source,
            description: $description,
            format: $format,
            minLength: $minLength,
            maxLength: $maxLength,
            minimum: $minimum,
            maximum: $maximum,
            pattern: $pattern,
            enum: $enum,
        );
    }

    /**
     * The declared type, defaulting to string.
     *
     * @param list<string> $rules
     * @return array{string, bool}
     *
     * @throws SchemaException
     */
    private static function type(string $path, array $rules): array
    {
        $found = null;
        foreach ($rules as $rule) {
            [$name] = self::split($rule);
            if (!isset(self::TYPES[$name])) {
                continue;
            }
            if ($found !== null && $found !== self::TYPES[$name]) {
                throw self::fail('rules.type_conflict', $path . ' declares more than one type.');
            }
            $found = self::TYPES[$name];
        }

        return [$found ?? 'string', $found !== null];
    }

    /**
     * `min:` and `max:` mean length on a string and value on a number, exactly as Laravel reads them.
     *
     * @return array{?int, int|float|null}
     *
     * @throws SchemaException
     */
    private static function bound(
        string $path,
        string $type,
        string $name,
        ?string $argument,
        ?int $length,
        int|float|null $value,
    ): array {
        if ($argument === null || !is_numeric($argument)) {
            throw self::fail('rules.malformed', $name . ': needs a number on ' . $path . '.');
        }

        return match ($type) {
            'string' => [self::count($path, $name, $argument), $value],
            'integer' => [$length, (int) $argument],
            'number' => [$length, (float) $argument],
            default => throw self::fail(
                'rules.bound_type',
                $name . ': applies to a string or a number, not ' . $type . ': ' . $path,
            ),
        };
    }

    /** @throws SchemaException */
    private static function count(string $path, string $name, ?string $argument): int
    {
        if ($argument === null || preg_match('/^\d+$/', $argument) !== 1) {
            throw self::fail('rules.malformed', $name . ': needs a whole number of characters on ' . $path . '.');
        }

        return (int) $argument;
    }

    /** @throws SchemaException */
    private static function pattern(string $path, ?string $argument): string
    {
        if ($argument === null || $argument === '') {
            throw self::fail('rules.malformed', 'regex: needs a pattern on ' . $path . '.');
        }
        if (@preg_match($argument, '') === false) {
            throw self::fail('rules.malformed', 'regex: is not a valid PCRE on ' . $path . ': ' . $argument);
        }

        return $argument;
    }

    /**
     * @return list<string|int|float|bool|null>
     *
     * @throws SchemaException
     */
    private static function enum(string $path, string $type, ?string $argument): array
    {
        if ($argument === null || $argument === '') {
            throw self::fail('rules.malformed', 'in: needs at least one value on ' . $path . '.');
        }

        $values = [];
        foreach (explode(',', $argument) as $value) {
            $values[] = self::literal($path, $type, trim($value));
        }

        return $values;
    }

    /** @throws SchemaException */
    private static function literal(string $path, string $type, ?string $argument): mixed
    {
        $raw = (string) $argument;
        if ($raw === 'null') {
            return null;
        }

        return match ($type) {
            'integer' => preg_match('/^-?\d+$/', $raw) === 1
                ? (int) $raw
                : throw self::fail('rules.malformed', $raw . ' is not an integer on ' . $path . '.'),
            'number' => is_numeric($raw)
                ? (float) $raw
                : throw self::fail('rules.malformed', $raw . ' is not a number on ' . $path . '.'),
            'boolean' => match (strtolower($raw)) {
                'true', '1' => true,
                'false', '0' => false,
                default => throw self::fail('rules.malformed', $raw . ' is not a boolean on ' . $path . '.'),
            },
            'array' => $raw === '[]'
                ? []
                : throw self::fail('rules.malformed', 'An array field only takes default:[] on ' . $path . '.'),
            'object' => throw self::fail('rules.malformed', 'An object field takes no default: on ' . $path . '.'),
            default => $raw,
        };
    }

    /** @throws SchemaException */
    private static function source(string $path, ?string $argument): string
    {
        if ($argument === null || !in_array($argument, Field::SOURCES, true)) {
            throw self::fail(
                'rules.unknown_source',
                'from: must name one of ' . implode(', ', Field::SOURCES) . ': ' . $path,
            );
        }

        return $argument;
    }

    /**
     * A rule is `name` or `name:argument`, split at the first colon only so that a pattern or a
     * description may contain colons of its own.
     *
     * @return array{string, ?string}
     */
    private static function split(string $rule): array
    {
        $position = strpos($rule, ':');
        if ($position === false) {
            return [$rule, null];
        }

        return [substr($rule, 0, $position), substr($rule, $position + 1)];
    }

    private static function fail(string $code, string $message): SchemaException
    {
        return new SchemaException($code, $message);
    }
}
