<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Product extends Model
{
    protected static string $table = 'products';

    public static function activeCatalogue(?string $category = null): array
    {
        $sql = 'SELECT * FROM products WHERE is_active = 1';
        $params = [];
        if ($category !== null && $category !== '') {
            $sql .= ' AND category = :c';
            $params['c'] = $category;
        }
        $sql .= ' ORDER BY name';
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function categories(): array
    {
        $stmt = self::pdo()->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
        return array_column($stmt->fetchAll(), 'category');
    }
}
