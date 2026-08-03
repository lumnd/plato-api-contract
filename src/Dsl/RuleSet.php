<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Dsl;

use InvalidArgumentException;

/**
 * A request or response body described the way a Laravel FormRequest describes one.
 *
 * Every entry is a field path mapped to the list of rules that hold for it. A path is either a
 * plain name, a dotted path into an object (`user.name`), or a `*` element of an array
 * (`tags.*`, `items.*.sku`). Nothing here interprets a rule: the DSL only records what the contract
 * said, and `RuleSetCompiler` turns it into the IR.
 *
 * The same form describes responses, where only the type, `nullable`, `optional`, `in:` and
 * `desc:` of a rule carry meaning - a response is projected, never validated.
 */
final readonly class RuleSet
{
    /** @var array<string, list<string>> field path to its rules, in declaration order */
    public array $fields;

    /** @param array<array-key, mixed> $fields */
    public function __construct(array $fields)
    {
        $normalized = [];
        foreach ($fields as $path => $rules) {
            if (!is_string($path) || !self::isPath($path)) {
                throw new InvalidArgumentException('Invalid field path: ' . (string) $path);
            }
            if (is_string($rules)) {
                $rules = self::split($rules);
            }
            if (!is_array($rules)) {
                throw new InvalidArgumentException(
                    'The rules of ' . $path . ' must be a list of strings or a pipe separated string.',
                );
            }

            $list = [];
            foreach ($rules as $rule) {
                if (!is_string($rule) || trim($rule) === '') {
                    throw new InvalidArgumentException('Every rule of ' . $path . ' must be a non empty string.');
                }
                $list[] = trim($rule);
            }
            if ($list === []) {
                throw new InvalidArgumentException($path . ' declares no rules; give it at least a type.');
            }

            $normalized[$path] = $list;
        }

        $this->fields = $normalized;
    }

    /**
     * The rules of a pipe separated string.
     *
     * Nothing here reads what a rule means, with one exception it cannot avoid: the argument of
     * `regex:` is a PCRE, and a PCRE may hold a `|` of its own. `required|regex:/^(foo|bar)$/` is
     * three rules to a plain explode() and two broken ones to the validator, so the pattern is
     * skipped over as the literal it is - delimiters, escapes and all - and only the pipes outside
     * it separate rules.
     *
     * @return list<string>
     */
    private static function split(string $rules): array
    {
        $list = [];
        $length = strlen($rules);
        $offset = 0;

        while ($offset <= $length) {
            $start = $offset;
            $offset += strspn($rules, " \t\n\r", $offset);
            if (substr($rules, $offset, 6) === 'regex:') {
                $offset = self::patternEnd($rules, $offset + 6);
            }

            $pipe = strpos($rules, '|', $offset);
            $end = $pipe === false ? $length : $pipe;
            $list[] = substr($rules, $start, $end - $start);
            $offset = $end + 1;
        }

        return $list;
    }

    /**
     * Where the PCRE starting at $offset ends, one past its closing delimiter.
     *
     * The delimiter is whatever character opens the pattern, with the four bracket pairs closing on
     * their mirror image and nesting the way PCRE lets them. A backslash escapes the next character,
     * which is how a pattern carries its own delimiter. The modifiers that follow are letters, so
     * the next `|` after this is a separator whatever they are.
     */
    private static function patternEnd(string $rules, int $offset): int
    {
        $length = strlen($rules);
        if ($offset >= $length) {
            return $offset;
        }

        $open = $rules[$offset];
        $close = match ($open) {
            '(' => ')',
            '[' => ']',
            '{' => '}',
            '<' => '>',
            default => $open,
        };

        $depth = 0;
        for ($i = $offset + 1; $i < $length; $i++) {
            $character = $rules[$i];
            if ($character === '\\') {
                $i++;
                continue;
            }
            if ($character === $open && $open !== $close) {
                $depth++;
                continue;
            }
            if ($character === $close) {
                if ($depth === 0) {
                    return $i + 1;
                }
                $depth--;
            }
        }

        // Unterminated, which FieldRules reports as a malformed pattern rather than this.
        return $length;
    }

    /**
     * A dot path of name segments, where `*` stands for the elements of an array.
     *
     * Names allow both cases: a request field is usually snake_case, while a response often has to
     * mirror the casing an existing client already reads.
     */
    private static function isPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        foreach (explode('.', $path) as $segment) {
            if ($segment !== '*' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                return false;
            }
        }

        return true;
    }
}
