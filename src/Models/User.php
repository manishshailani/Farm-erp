<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';

    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM users WHERE email = :e');
        $stmt->execute(['e' => $email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<string> role names for this user */
    public static function roleNames(int $userId): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :u ORDER BY r.name'
        );
        $stmt->execute(['u' => $userId]);
        return array_column($stmt->fetchAll(), 'name');
    }

    public static function create(string $name, string $email, string $password, array $roleIds): int
    {
        $now = date('Y-m-d H:i:s');
        return (int) \App\Core\Database::transaction(function ($pdo) use ($name, $email, $password, $roleIds, $now) {
            $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at) VALUES (:n, :e, :p, 1, :c, :u)'
            )->execute([
                'n' => $name,
                'e' => $email,
                'p' => password_hash($password, PASSWORD_BCRYPT),
                'c' => $now,
                'u' => $now,
            ]);
            $id = (int) \App\Core\Database::lastInsertId();
            $assign = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)');
            foreach ($roleIds as $roleId) {
                $assign->execute(['u' => $id, 'r' => (int) $roleId]);
            }
            return $id;
        });
    }

    public static function setActive(int $id, bool $active): void
    {
        self::pdo()->prepare('UPDATE users SET is_active = :a, updated_at = :u WHERE id = :id')
            ->execute(['a' => $active ? 1 : 0, 'u' => date('Y-m-d H:i:s'), 'id' => $id]);
    }

    /**
     * Overwrites the stored password hash for an existing user.
     *
     * Always re-hashes with password_hash() (bcrypt on this PHP build) --
     * never stores or logs the plaintext password anywhere, including in
     * the audit trail (callers must pass only non-secret context to
     * Audit::log(), such as "self-service" or the acting admin's id, never
     * the password itself).
     */
    public static function updatePassword(int $id, string $newPassword): void
    {
        self::pdo()->prepare('UPDATE users SET password_hash = :p, updated_at = :u WHERE id = :id')
            ->execute([
                'p' => password_hash($newPassword, PASSWORD_BCRYPT),
                'u' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]);
    }

    public static function syncRoles(int $userId, array $roleIds): void
    {
        $pdo = self::pdo();
        $pdo->prepare('DELETE FROM user_roles WHERE user_id = :u')->execute(['u' => $userId]);
        $assign = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)');
        foreach ($roleIds as $roleId) {
            $assign->execute(['u' => $userId, 'r' => (int) $roleId]);
        }
    }
}
