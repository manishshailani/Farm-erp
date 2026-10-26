<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF protection using the synchronizer-token pattern.
 *
 * A single per-session token is issued and embedded in every state-changing
 * HTML form via Csrf::field(); Csrf::verifyRequest() (wired in as
 * middleware for POST/PUT/PATCH/DELETE routes) rejects the request with
 * HTTP 419 if the token is missing or does not match, using
 * hash_equals() to avoid timing side-channels.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return "<input type=\"hidden\" name=\"_csrf\" value=\"{$token}\">";
    }

    public static function verify(string $submitted): bool
    {
        $token = Session::get(self::SESSION_KEY);
        return is_string($token) && $token !== '' && hash_equals($token, $submitted);
    }

    public static function middleware(): callable
    {
        return static function (Request $request, array $args, callable $next): void {
            $method = $request->method();
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $submitted = (string) $request->input('_csrf', '');
                if (!self::verify($submitted)) {
                    http_response_code(419);
                    View::render('errors/419', [], '');
                    return;
                }
            }
            $next();
        };
    }
}
