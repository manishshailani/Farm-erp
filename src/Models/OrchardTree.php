<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class OrchardTree extends Model
{
    protected static string $table = 'orchard_trees';

    /** @return list<array<string,mixed>> */
    public static function forBlock(int $blockId): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT * FROM orchard_trees WHERE block_id = :b ORDER BY row_number, position_in_row, id'
        );
        $stmt->execute(['b' => $blockId]);
        return $stmt->fetchAll();
    }

    public static function codeExists(string $treeCode): bool
    {
        $stmt = self::pdo()->prepare('SELECT 1 FROM orchard_trees WHERE tree_code = :c');
        $stmt->execute(['c' => $treeCode]);
        return $stmt->fetchColumn() !== false;
    }
}
