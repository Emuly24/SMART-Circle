<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Read-only settings: config/app.php defaults merged with secrets.php overrides.
 */
final class Config
{
    /**
     * @param array<string, mixed> $values
     * @param string $secretsError why secrets.php could not be read ('' = fine); never contains secret values
     */
    public function __construct(private readonly array $values, public readonly string $secretsError = '')
    {
    }

    public static function load(string $defaultsFile, ?string $secretsFile = null): self
    {
        $values = require $defaultsFile;
        $error = '';

        if ($secretsFile !== null && is_file($secretsFile)) {
            try {
                $secrets = require $secretsFile;
            } catch (\Throwable $e) {
                // ParseError (missing comma or quote), or Error: curly quotes make PHP read a
                // word as an undefined constant. Either way: explain, never show a blank page.
                $secrets = null;
                $error = self::describeMistake((string) @file_get_contents($secretsFile), $e->getLine());
            }
            if (is_array($secrets)) {
                $values = self::merge($values, $secrets);
            } elseif ($error === '') {
                $error = 'secrets.php must start with <?php and contain "return [ ... ];", like secrets.example.php.';
            }
        }

        return new self($values, $error);
    }

    /**
     * A helpful message about a typing mistake in secrets.php. It names the line and the
     * likely cause, but never repeats the file's contents (they are passwords).
     */
    public static function describeMistake(string $source, int $line): string
    {
        $message = "secrets.php has a typing mistake on or just before line $line.";
        if (preg_match('/[\x{2018}\x{2019}\x{201C}\x{201D}]/u', $source) === 1) {
            return $message . ' It contains curly quotes (‘ ’ or “ ”), which iPads and phones insert while typing.'
                . ' Replace them with straight quotes (\'), or turn off Settings > General > Keyboard > Smart Punctuation and type them again.';
        }
        if (!str_starts_with(ltrim($source, "\xEF\xBB\xBF \t\r\n"), '<?php')) {
            return $message . ' The file must start with <?php.';
        }

        return $message . ' Check for a missing comma at the end of the line before, or a missing quote.';
    }

    /** Dot notation: get('db.host'). */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $base[$key] = is_array($value) && isset($base[$key]) && is_array($base[$key])
                ? self::merge($base[$key], $value)
                : $value;
        }

        return $base;
    }
}
