<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class OrchardBlock extends Model
{
    protected static string $table = 'orchard_blocks';

    /** @return list<array<string,mixed>> each row carries a live tree_count */
    public static function withTreeCounts(): array
    {
        $stmt = self::pdo()->query(
            'SELECT b.*, COUNT(t.id) AS tree_count
             FROM orchard_blocks b
             LEFT JOIN orchard_trees t ON t.block_id = b.id
             GROUP BY b.id
             ORDER BY b.name'
        );
        return $stmt->fetchAll();
    }
}
