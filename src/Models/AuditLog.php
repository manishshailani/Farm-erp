<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    /**
     * @param array{entity_type?: string, user_id?: int, q?: string} $filters
     * @return list<array<string,mixed>>
     */
    public static function search(array $filters, int $limit = 200): array
    {
        $sql = 'SELECT a.*, u.name AS user_name
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['entity_type'])) {
            $sql .= ' AND a.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= ' AND a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (a.action LIKE :q OR a.notes LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql .= ' ORDER BY a.id DESC LIMIT ' . (int) $limit;
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return list<string> distinct entity_type values seen so far, for the filter dropdown */
    public static function distinctEntityTypes(): array
    {
        $stmt = self::pdo()->query('SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type');
        return array_column($stmt->fetchAll(), 'entity_type');
    }
}
