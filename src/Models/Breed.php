<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Breed extends Model
{
    protected static string $table = 'breeds';

    public static function forSpecies(int $speciesId): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM breeds WHERE species_id = :s ORDER BY name');
        $stmt->execute(['s' => $speciesId]);
        return $stmt->fetchAll();
    }
}
