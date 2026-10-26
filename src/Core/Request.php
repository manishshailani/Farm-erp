<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thin wrapper around superglobals so controllers never read
 * $_GET/$_POST/$_SERVER directly (keeps input handling centralized and
 * testable, and is the single place that would change if this ever moved
 * behind a different web server).
 */
final class Request
{
    /** @param array<string,mixed> $get @param array<string,mixed> $post @param array<string,mixed> $server @param array<string,mixed> $files */
    public function __construct(
        private array $get,
        private array $post,
        private array $server,
        private array $files,
        private array $cookies,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE);
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
        // Support HTML forms (which only support GET/POST) spoofing
        // PUT/PATCH/DELETE via a hidden _method field, a common and safe
        // convention as long as it is only honoured on POST requests.
        if ($method === 'POST' && isset($this->post['_method'])) {
            $spoof = strtoupper((string) $this->post['_method']);
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoof;
            }
        }
        return $method;
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        // Allow the app to live in a sub-directory (e.g. /farm-erp/public)
        // by stripping the script's own directory from the path.
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($this->server['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }
        if ($path === '' ) {
            $path = '/';
        }
        return $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return trim((string) $value);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key);
        return in_array($value, ['1', 'true', 'on', 'yes', 1, true], true);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isHttps(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
