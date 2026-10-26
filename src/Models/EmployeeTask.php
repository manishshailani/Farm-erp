<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class EmployeeTask extends Model
{
    protected static string $table = 'employee_tasks';

    public static function withEmployee(array $filters = []): array
    {
        $sql = 'SELECT t.*, e.name AS employee_name FROM employee_tasks t
                LEFT JOIN employees e ON e.id = t.assigned_to WHERE 1=1';
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' AND t.status = :status';
            $params['status'] = $filters['status'];
        }
        $sql .= ' ORDER BY (t.due_date IS NULL), t.due_date ASC, t.id DESC';
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
