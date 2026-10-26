<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class OrchardHarvest extends Model
{
    protected static string $table = 'orchard_harvests';

    /** @return list<array<string,mixed>> */
    public static function forBlock(int $blockId, int $limit = 200): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT h.*, t.tree_code
             FROM orchard_harvests h
             LEFT JOIN orchard_trees t ON t.id = h.tree_id
             WHERE h.block_id = :b
             ORDER BY h.harvest_date DESC, h.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['b' => $blockId]);
        return $stmt->fetchAll();
    }

    /** @return array{total_kg: float, harvest_count: int} totals across every block, used on the orchard index page */
    public static function totals(): array
    {
        $row = self::pdo()->query(
            'SELECT COALESCE(SUM(quantity_kg),0) AS total_kg, COUNT(*) AS harvest_count FROM orchard_harvests'
        )->fetch();
        return ['total_kg' => (float) $row['total_kg'], 'harvest_count' => (int) $row['harvest_count']];
    }
}
