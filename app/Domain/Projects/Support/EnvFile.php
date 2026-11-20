<?php

namespace App\Domain\Projects\Support;

use InvalidArgumentException;

/**
 * A .env file edited without ever exposing its values (PRJ-4, spec 9.1):
 * the UI only receives variable names; values can be set or removed.
 *
 * Comments, blank lines and ordering are preserved.
 */
final class EnvFile
{
    public const KEY_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * Each line is either raw text (comment/blank/unparsed) or a key=value entry.
     *
     * @var list<array{key: string|null, line: string}>
     */
    private array $lines = [];

    private function __construct() {}

    public static function parse(?string $content): self
    {
        $file = new self;

        if ($content === null || $content === '') {
            return $file;
        }

        foreach (preg_split('/\r\n|\n|\r/', rtrim($content, "\r\n")) ?: [] as $line) {
            $file->lines[] = ['key' => self::keyOf($line), 'line' => $line];
        }

        return $file;
    }

    /**
     * Variable names in file order, without duplicates.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];

        foreach ($this->lines as $line) {
            if ($line['key'] !== null && ! in_array($line['key'], $keys, true)) {
                $keys[] = $line['key'];
            }
        }

        return $keys;
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /**
     * Raw values, used only to mask secrets in deployment output.
     *
     * @return list<string>
     */
    public function values(): array
    {
        $values = [];

        foreach ($this->lines as $line) {
            if ($line['key'] !== null) {
                $values[] = self::unquote(substr($line['line'], strpos($line['line'], '=') + 1));
            }
        }

        return $values;
    }

    /**
     * Set a variable: replaces it in place if present, appends it otherwise.
     */
    public function set(string $key, string $value): self
    {
        self::assertValidKey($key);

        if (preg_match('/[\r\n]/', $value) === 1) {
            throw new InvalidArgumentException('Values cannot contain line breaks.');
        }

        $line = $key.'='.self::quote($value);
        $replaced = false;
        $lines = [];

        foreach ($this->lines as $existing) {
            if ($existing['key'] === $key) {
                if (! $replaced) {
                    $lines[] = ['key' => $key, 'line' => $line];
                    $replaced = true;
                }

                continue;
            }

            $lines[] = $existing;
        }

        if (! $replaced) {
            $lines[] = ['key' => $key, 'line' => $line];
        }

        $this->lines = $lines;

        return $this;
    }

    public function remove(string $key): self
    {
        $this->lines = array_values(array_filter(
            $this->lines,
            fn (array $line): bool => $line['key'] !== $key,
        ));

        return $this;
    }

    public function toString(): string
    {
        if ($this->lines === []) {
            return '';
        }

        return implode("\n", array_column($this->lines, 'line'))."\n";
    }

    public static function assertValidKey(string $key): void
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("Invalid variable name [{$key}].");
        }
    }

    private static function keyOf(string $line): ?string
    {
        $trimmed = ltrim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            return null;
        }

        if (str_starts_with($trimmed, 'export ')) {
            $trimmed = ltrim(substr($trimmed, 7));
        }

        $position = strpos($trimmed, '=');

        if ($position === false) {
            return null;
        }

        $key = trim(substr($trimmed, 0, $position));

        return preg_match(self::KEY_PATTERN, $key) === 1 ? $key : null;
    }

    /**
     * Quote a value so phpdotenv reads it back literally.
     */
    private static function quote(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.,:\/@+=-]*$/', $value) === 1) {
            return $value;
        }

        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    private static function unquote(string $raw): string
    {
        $raw = trim($raw);

        if (strlen($raw) >= 2 && ($raw[0] === '"' || $raw[0] === "'") && $raw[-1] === $raw[0]) {
            $inner = substr($raw, 1, -1);

            return $raw[0] === '"' ? stripcslashes($inner) : $inner;
        }

        return $raw;
    }
}
