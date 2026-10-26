<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Role-based access control.
 *
 * Data model: users -> user_roles -> roles -> role_permissions -> permissions.
 * Permissions are plain string keys like "livestock.manage",
 * "finance.view", "orders.refund" -- see database/seeds/002_rbac_seed.php
 * for the full seeded list and database/migrations for the schema.
 *
 * Critically, every protected *action* (controller method), not just every
 * navigation link, is expected to call Permission::require() or be wrapped
 * with Permission::middleware() -- hiding a button in the UI is not
 * access control.
 */
final class Permission
{
    /** @var array<int, list<string>> user_id => permission keys, cached per-request */
    private static array $cache = [];

    /** @return list<string> */
    public static function forUser(int $userId): array
    {
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT DISTINCT p.key AS permission_key
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $keys = array_map(static fn (array $row) => (string) $row['permission_key'], $stmt->fetchAll());

        self::$cache[$userId] = $keys;
        return $keys;
    }

    public static function userHas(int $userId, string $permission): bool
    {
        return in_array($permission, self::forUser($userId), true);
    }

    public static function currentUserHas(string $permission): bool
    {
        $userId = Auth::id();
        return $userId !== null && self::userHas($userId, $permission);
    }

    /**
     * Route middleware factory: Permission::middleware('livestock.manage').
     * Must run after Auth::middleware() in the chain so Auth::id() is set.
     */
    public static function middleware(string $permission): callable
    {
        return static function (Request $request, array $args, callable $next) use ($permission): void {
            if (!self::currentUserHas($permission)) {
                http_response_code(403);
                View::render('errors/403', ['permission' => $permission], '');
                return;
            }
            $next();
        };
    }
}
