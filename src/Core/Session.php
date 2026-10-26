<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Wraps PHP's native session handling with the security settings the
 * project's engineering rules require: HttpOnly + SameSite cookies, a
 * configurable Secure flag (on only when actually served over HTTPS so
 * cookies are not silently dropped on a plain-HTTP XAMPP install), and
 * session-id regeneration on privilege change (login) to prevent session
 * fixation.
 */
final class Session
{
    private static bool $started = false;

    public static function start(bool $secure = false): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $name = (string) Env::get('SESSION_NAME', 'farm_erp_session');
        $lifetimeMinutes = (int) Env::get('SESSION_LIFETIME_MINUTES', 120);

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetimeMinutes * 60,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;

        // Idle timeout, independent of the cookie's own lifetime (a cookie
        // can be copied/replayed; we also expire server-side state).
        $now = time();
        $last = $_SESSION['_last_activity'] ?? $now;
        if ($now - (int) $last > $lifetimeMinutes * 60) {
            self::destroy();
            session_start();
        }
        $_SESSION['_last_activity'] = $now;
    }

    public static function regenerate(): void
    {
        // Guard against the (harmless but noisy) PHP warning when this is
        // called outside a real HTTP session -- e.g. the automated test
        // suite calls Auth::login() directly against a bare $_SESSION
        // array with no session_start() behind it.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /** Reads a flash value and removes it (one-time display, e.g. form success banners). */
    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}
