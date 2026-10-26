<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env file loader.
 *
 * Deliberately dependency-free: the sandbox this project was first built in
 * has no access to Packagist/Composer package downloads, so we do not take
 * a dependency on vlucas/phpdotenv. This parser supports the subset of
 * dotenv syntax this project actually uses: KEY=value lines, optional
 * double-quoted values, '#' comments, and blank lines. It does not support
 * multiline values or variable interpolation.
 *
 * On a machine with normal internet access you may swap this for
 * vlucas/phpdotenv without changing any call site, since both populate
 * $_ENV / getenv().
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            // Not fatal: Env::get() falls back to defaults / real environment
            // variables, which covers container/CI deployments that inject
            // configuration directly instead of via a .env file.
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '') {
                continue;
            }

            // Strip matching surrounding quotes.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Do not overwrite a value already present in the real
            // environment (lets ops override .env via actual env vars).
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }
}
