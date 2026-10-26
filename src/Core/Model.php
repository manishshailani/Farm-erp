<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Tiny base class with the handful of query helpers every model in this
 * project needs. This is intentionally not an ORM: every non-trivial
 * query is still hand-written SQL in the model/service that needs it, so
 * the actual query executed is always visible and reviewable (per the
 * project's reliability-first rules, nothing here hides a generated
 * query behind "magic").
 */
abstract class Model
{
    protected static string $table = '';

    protected static function pdo(): PDO
    {
        return Database::connection();
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM ' . static::$table . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $orderBy = 'id DESC', int $limit = 500): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM ' . static::$table . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int) $limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        self::pdo()->prepare('DELETE FROM ' . static::$table . ' WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(string $where = '1=1', array $params = []): int
    {
        $stmt = self::pdo()->prepare('SELECT COUNT(*) AS c FROM ' . static::$table . ' WHERE ' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetch()['c'];
    }
}
