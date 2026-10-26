<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

/**
 * Simple DB-backed login throttling (spec section 15: "rate limiting or
 * throttling for authentication-sensitive endpoints"). Keyed on
 * email+IP together so one attacker IP hammering many email addresses,
 * or many IPs hammering one email address, are both slowed down.
 */
final class LoginThrottle
{
    public static function tooManyAttempts(string $email, string $ip): bool
    {
        $max = (int) Env::get('AUTH_MAX_ATTEMPTS', 5);
        $lockoutMinutes = (int) Env::get('AUTH_LOCKOUT_MINUTES', 15);
        $since = date('Y-m-d H:i:s', time() - $lockoutMinutes * 60);

        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS c FROM login_attempts
             WHERE email = :email AND ip_address = :ip AND successful = 0 AND attempted_at >= :since"
        );
        $stmt->execute(['email' => $email, 'ip' => $ip, 'since' => $since]);
        return (int) $stmt->fetch()['c'] >= $max;
    }

    public static function record(string $email, string $ip, bool $successful): void
    {
        Database::connection()->prepare(
            'INSERT INTO login_attempts (email, ip_address, successful, attempted_at) VALUES (:email, :ip, :s, :t)'
        )->execute(['email' => $email, 'ip' => $ip, 's' => $successful ? 1 : 0, 't' => date('Y-m-d H:i:s')]);
    }
}
