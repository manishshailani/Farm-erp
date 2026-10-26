<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PondWaterQualityReading extends Model
{
    protected static string $table = 'pond_water_quality_readings';

    /** @return list<array<string,mixed>> */
    public static function forPond(int $pondId, int $limit = 100): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT * FROM pond_water_quality_readings WHERE pond_id = :p ORDER BY measured_at DESC LIMIT ' . (int) $limit
        );
        $stmt->execute(['p' => $pondId]);
        return $stmt->fetchAll();
    }

    public static function latestForPond(int $pondId): ?array
    {
        $stmt = self::pdo()->prepare(
            'SELECT * FROM pond_water_quality_readings WHERE pond_id = :p ORDER BY measured_at DESC LIMIT 1'
        );
        $stmt->execute(['p' => $pondId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
