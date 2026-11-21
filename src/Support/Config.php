<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Read-only settings: config/app.php defaults merged with secrets.php overrides.
 */
final class Config
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values)
    {
    }

    public static function load(string $defaultsFile, ?string $secretsFile = null): self
    {
        $values = require $defaultsFile;

        if ($secretsFile !== null && is_file($secretsFile)) {
            $secrets = require $secretsFile;
            if (is_array($secrets)) {
                $values = self::merge($values, $secrets);
            }
        }

        return new self($values);
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
