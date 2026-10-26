<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Pond extends Model
{
    protected static string $table = 'ponds';

    /** @return list<array<string,mixed>> each row carries the count of currently-growing stocking batches */
    public static function withActiveStockingCounts(): array
    {
        $stmt = self::pdo()->query(
            "SELECT p.*,
                    SUM(CASE WHEN b.status = 'growing' THEN 1 ELSE 0 END) AS active_batches
             FROM ponds p
             LEFT JOIN pond_stocking_batches b ON b.pond_id = p.id
             GROUP BY p.id
             ORDER BY p.name"
        );
        return $stmt->fetchAll();
    }

    public static function nameExists(string $name): bool
    {
        $stmt = self::pdo()->prepare('SELECT 1 FROM ponds WHERE name = :n');
        $stmt->execute(['n' => $name]);
        return $stmt->fetchColumn() !== false;
    }
}
