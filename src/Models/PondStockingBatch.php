<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PondStockingBatch extends Model
{
    protected static string $table = 'pond_stocking_batches';

    /** @return list<array<string,mixed>> */
    public static function forPond(int $pondId): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT * FROM pond_stocking_batches WHERE pond_id = :p ORDER BY stocking_date DESC, id DESC'
        );
        $stmt->execute(['p' => $pondId]);
        return $stmt->fetchAll();
    }
}
