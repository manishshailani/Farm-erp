<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class InventoryItem extends Model
{
    protected static string $table = 'inventory_items';

    public static function withCategory(): array
    {
        $stmt = self::pdo()->query(
            'SELECT i.*, c.name AS category_name FROM inventory_items i
             LEFT JOIN inventory_categories c ON c.id = i.category_id
             ORDER BY i.name'
        );
        return $stmt->fetchAll();
    }

    public static function lowStock(): array
    {
        $stmt = self::pdo()->query(
            'SELECT * FROM inventory_items WHERE current_quantity <= reorder_threshold AND is_active = 1 ORDER BY name'
        );
        return $stmt->fetchAll();
    }

    public static function batches(int $itemId): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM inventory_batches WHERE item_id = :i ORDER BY received_date');
        $stmt->execute(['i' => $itemId]);
        return $stmt->fetchAll();
    }

    public static function movements(int $itemId, int $limit = 100): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM stock_movements WHERE item_id = :i ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit);
        $stmt->execute(['i' => $itemId]);
        return $stmt->fetchAll();
    }
}
