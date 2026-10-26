<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Role extends Model
{
    protected static string $table = 'roles';

    public static function allWithPermissionCount(): array
    {
        $stmt = self::pdo()->query(
            'SELECT r.*, COUNT(rp.permission_id) AS permission_count
             FROM roles r LEFT JOIN role_permissions rp ON rp.role_id = r.id
             GROUP BY r.id ORDER BY r.name'
        );
        return $stmt->fetchAll();
    }

    public static function permissionIds(int $roleId): array
    {
        $stmt = self::pdo()->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :r');
        $stmt->execute(['r' => $roleId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'permission_id'));
    }

    public static function syncPermissions(int $roleId, array $permissionIds): void
    {
        $pdo = self::pdo();
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :r')->execute(['r' => $roleId]);
        $assign = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:r, :p)');
        foreach ($permissionIds as $permissionId) {
            $assign->execute(['r' => $roleId, 'p' => (int) $permissionId]);
        }
    }
}
