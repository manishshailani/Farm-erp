<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Animal extends Model
{
    protected static string $table = 'animals';

    /** @return list<array<string,mixed>> */
    public static function search(array $filters, int $limit = 200): array
    {
        $sql = 'SELECT a.*, s.name AS species_name, b.name AS breed_name
                FROM animals a
                JOIN species s ON s.id = a.species_id
                LEFT JOIN breeds b ON b.id = a.breed_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['species_id'])) {
            $sql .= ' AND a.species_id = :species_id';
            $params['species_id'] = (int) $filters['species_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (a.animal_code LIKE :q OR a.name LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql .= ' ORDER BY a.id DESC LIMIT ' . (int) $limit;
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function withRelations(int $id): ?array
    {
        $stmt = self::pdo()->prepare(
            'SELECT a.*, s.name AS species_name, b.name AS breed_name
             FROM animals a
             JOIN species s ON s.id = a.species_id
             LEFT JOIN breeds b ON b.id = a.breed_id
             WHERE a.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function activeTag(int $animalId): ?string
    {
        $stmt = self::pdo()->prepare('SELECT tag_number FROM rfid_tags WHERE animal_id = :a AND is_active = 1');
        $stmt->execute(['a' => $animalId]);
        $row = $stmt->fetch();
        return $row === false ? null : (string) $row['tag_number'];
    }

    public static function weightHistory(int $animalId): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM animal_weight_events WHERE animal_id = :a ORDER BY recorded_at DESC');
        $stmt->execute(['a' => $animalId]);
        return $stmt->fetchAll();
    }

    public static function lifecycleEvents(int $animalId): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM animal_lifecycle_events WHERE animal_id = :a ORDER BY event_date DESC, id DESC');
        $stmt->execute(['a' => $animalId]);
        return $stmt->fetchAll();
    }

    public static function vaccinationHistory(int $animalId): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM vaccinations WHERE animal_id = :a ORDER BY administered_date DESC');
        $stmt->execute(['a' => $animalId]);
        return $stmt->fetchAll();
    }

    public static function nextCodeSuffix(): int
    {
        $stmt = self::pdo()->query('SELECT COUNT(*) AS c FROM animals');
        return ((int) $stmt->fetch()['c']) + 1;
    }
}
