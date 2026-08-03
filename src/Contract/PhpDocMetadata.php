<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Contract;

use InvalidArgumentException;
use Lumnd\PlatoApiContract\Dsl\Endpoint;

final class PhpDocMetadata
{
    private const ENDPOINT_HELPERS = ['endpoint', 'get', 'post', 'put', 'patch', 'delete'];

    /**
     * @return list<array{code: string, line: int, message: string}>
     */
    public function validateContractFile(string $file): array
    {
        if (!is_readable($file)) {
            return [];
        }

        $source = file_get_contents($file);
        if ($source === false) {
            return [];
        }

        $tokens = token_get_all($source);
        $issues = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_DOC_COMMENT || !$this->hasEndpointTag($token[1])) {
                continue;
            }

            $line = $token[2];
            $next = $this->nextMeaningfulToken($tokens, $index + 1);
            if ($this->startsPromotedProperty($next)) {
                continue;
            }

            $issues = [...$issues, ...$this->validateEndpointComment($token[1], $line)];
            if (!$this->startsEndpoint($next)) {
                $issues[] = [
                    'code' => 'endpoint.doc_not_attached',
                    'line' => $line,
                    'message' => 'Endpoint PHPDoc must be immediately followed by an endpoint helper call.',
                ];
            }
        }

        return $issues;
    }

    /** @param array{int, string, int}|string|null $token */
    private function startsEndpoint(array|string|null $token): bool
    {
        return is_array($token)
            && $token[0] === T_STRING
            && in_array(strtolower($token[1]), self::ENDPOINT_HELPERS, true);
    }

    /** @param array{int, string, int}|string|null $token */
    private function startsPromotedProperty(array|string|null $token): bool
    {
        return is_array($token) && in_array($token[0], [T_PUBLIC, T_ATTRIBUTE], true);
    }

    /** @return array{title?: string, description?: string} */
    public function endpoint(Endpoint $endpoint): array
    {
        if ($endpoint->sourceFile === null || $endpoint->sourceLine === null || !is_readable($endpoint->sourceFile)) {
            return [];
        }

        $source = file_get_contents($endpoint->sourceFile);
        if ($source === false) {
            return [];
        }

        $lines = preg_split('/\R/u', $source);
        if ($lines === false || $endpoint->sourceLine < 1 || $endpoint->sourceLine > count($lines)) {
            return [];
        }

        $prefix = implode("\n", array_slice($lines, 0, $endpoint->sourceLine - 1));
        if (preg_match('/(\/\*\*(?:(?!\/\*\*)[\s\S])*?\*\/)\s*$/u', $prefix, $matches) !== 1) {
            return [];
        }

        $tags = $this->tags($matches[1]);
        $metadata = [];
        if (($tags['title'] ?? '') !== '') {
            $metadata['title'] = $tags['title'];
        }
        $description = $tags['desc'] ?? $tags['description'] ?? '';
        if ($description !== '') {
            $metadata['description'] = $description;
        }

        return $metadata;
    }

    /** @return array{description: string, must: bool|null} */
    public function field(string|false $comment, string $context): array
    {
        if (!is_string($comment) || $comment === '') {
            return ['description' => '', 'must' => null];
        }

        $this->validateFieldComment($comment, $context);
        $tags = $this->tags($comment);
        $description = $tags['desc'] ?? $tags['description'] ?? '';
        $must = match (strtolower($tags['must'] ?? '')) {
            'true' => true,
            'false' => false,
            default => null,
        };

        return ['description' => $description, 'must' => $must];
    }

    /** @return array<string, string> */
    private function tags(string $comment): array
    {
        $tags = [];
        $current = null;
        foreach ($this->lines($comment) as $line) {
            if (preg_match('/^@([a-z][a-z0-9_-]*)\s*(.*)$/iu', $line, $matches) === 1) {
                $current = strtolower($matches[1]);
                $tags[$current] = $this->unquote(trim($matches[2]));
                continue;
            }
            if ($current !== null && $line !== '') {
                $tags[$current] = trim($tags[$current] . ' ' . $line);
            }
        }

        return $tags;
    }

    private function hasEndpointTag(string $comment): bool
    {
        return preg_match('/(?:^|\R)\s*\*?\s*(?:-\s+)?@(title|desc|description)\b/iu', $comment) === 1;
    }

    private function validateFieldComment(string $comment, string $context): void
    {
        $seen = [];
        foreach ($this->lines($comment) as $line) {
            if (preg_match('/^@([a-z][a-z0-9_-]*)\b\s*(.*)$/iu', $line, $matches) !== 1) {
                continue;
            }

            $tag = strtolower($matches[1]);
            if (!in_array($tag, ['desc', 'description', 'must'], true)) {
                continue;
            }
            $canonical = $tag === 'description' ? 'desc' : $tag;
            if (isset($seen[$canonical])) {
                throw new InvalidArgumentException(
                    $context . ' PHPDoc tag @' . $canonical . ' may only appear once.',
                );
            }
            $seen[$canonical] = true;
            $value = trim($matches[2]);
            if ($canonical === 'desc' && $value === '') {
                throw new InvalidArgumentException($context . ' PHPDoc tag @' . $tag . ' must not be empty.');
            }
            if ($canonical === 'must' && !in_array(strtolower($value), ['true', 'false'], true)) {
                throw new InvalidArgumentException(
                    $context . ' PHPDoc tag @must must be either true or false.',
                );
            }
        }
    }

    /** @return list<array{code: string, line: int, message: string}> */
    private function validateEndpointComment(string $comment, int $line): array
    {
        $issues = [];
        $seen = [];
        foreach ($this->lines($comment) as $offset => $docLine) {
            if (preg_match('/^@([a-z][a-z0-9_-]*)\b\s*(.*)$/iu', $docLine, $matches) !== 1) {
                continue;
            }

            $tag = strtolower($matches[1]);
            $tagLine = $line + $offset;
            if (!in_array($tag, ['title', 'desc', 'description'], true)) {
                $issues[] = [
                    'code' => 'endpoint.doc_unknown_tag',
                    'line' => $tagLine,
                    'message' => 'Unsupported endpoint PHPDoc tag @' . $tag . '.',
                ];
                continue;
            }
            $canonical = $tag === 'description' ? 'desc' : $tag;
            if (isset($seen[$canonical])) {
                $issues[] = [
                    'code' => 'endpoint.doc_duplicate_tag',
                    'line' => $tagLine,
                    'message' => 'Endpoint PHPDoc tag @' . $canonical . ' may only appear once.',
                ];
            }
            $seen[$canonical] = true;
            if (trim($matches[2]) === '') {
                $issues[] = [
                    'code' => 'endpoint.doc_empty_tag',
                    'line' => $tagLine,
                    'message' => 'Endpoint PHPDoc tag @' . $tag . ' must have a value on the same line.',
                ];
            }
        }

        return $issues;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     * @return array{int, string, int}|string|null
     */
    private function nextMeaningfulToken(array $tokens, int $offset): array|string|null
    {
        for ($index = $offset, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }
            return $token;
        }

        return null;
    }

    /** @return list<string> */
    private function lines(string $comment): array
    {
        $comment = preg_replace('/^\s*\/\*\*|\*\/\s*$/u', '', $comment) ?? $comment;
        $lines = preg_split('/\R/u', $comment) ?: [];

        return array_map(
            static function (string $line): string {
                $line = preg_replace('/^\s*\*?\s?/u', '', $line) ?? $line;
                return trim(preg_replace('/^-\s+(?=@)/u', '', $line) ?? $line);
            },
            $lines,
        );
    }

    private function unquote(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];
        return ($first === $last && ($first === '"' || $first === "'"))
            ? substr($value, 1, -1)
            : $value;
    }
}
