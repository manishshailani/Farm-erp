<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Authentication: login/logout, "current user", and the permission checks
 * that sit on top of RBAC (see Permission::class for the role/permission
 * data model).
 *
 * Password hashing uses PHP's password_hash()/password_verify() (bcrypt by
 * default on this PHP build), never a custom or reversible scheme.
 * Session ID is regenerated on every successful login to prevent session
 * fixation attacks.
 */
final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if ($user === null || !$user['is_active']) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        self::login($user);
        return true;
    }

    /** @param array<string,mixed> $user */
    public static function login(array $user): void
    {
        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('user_name', (string) $user['name']);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    /**
     * Verifies a plaintext password against a user's stored hash, for
     * self-service "confirm your current password before changing it"
     * flows. Does not touch the session -- it is a pure check, not a login.
     *
     * @param array<string,mixed> $user a row from the users table (must
     *     include password_hash)
     */
    public static function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, (string) $user['password_hash']);
    }

    public static function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id === null ? null : (int) $id;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        static $cache = [];
        if (!array_key_exists($id, $cache)) {
            $cache[$id] = User::find($id);
        }
        return $cache[$id];
    }

    public static function middleware(): callable
    {
        return static function (Request $request, array $args, callable $next): void {
            if (!self::check()) {
                Session::flash('error', 'Please sign in to continue.');
                self::redirectToLogin();
                return;
            }
            $user = self::user();
            if ($user === null || !$user['is_active']) {
                self::logout();
                self::redirectToLogin();
                return;
            }
            $next();
        };
    }

    private static function redirectToLogin(): void
    {
        $base = (string) Env::get('APP_URL', '');
        header('Location: ' . self::basePath() . '/login');
    }

    private static function basePath(): string
    {
        return rtrim((string) ($_SERVER['SCRIPT_NAME'] ? dirname($_SERVER['SCRIPT_NAME']) : ''), '/');
    }
}
