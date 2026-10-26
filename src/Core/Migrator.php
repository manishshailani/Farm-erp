<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Minimal, dependency-free SQL migration runner.
 *
 * Migrations live in database/migrations/mysql/*.sql and
 * database/migrations/sqlite/*.sql as plain .sql files, one logical
 * migration each, named "NNN_description.sql" so lexical sort == intended
 * order. The two directories must define equivalent schemas (same table
 * and column names/types as far as each engine allows) -- see
 * docs/DATABASE_PORTABILITY.md for exactly where they differ and why.
 * Migrator picks the directory matching Database::driver().
 *
 * Applied migrations are recorded in a "migrations" table so re-running
 * this command is always safe (idempotent) -- already-applied files are
 * skipped.
 */
final class Migrator
{
    public static function migrationsPath(): string
    {
        $driver = Database::driver();
        return dirname(__DIR__, 2) . '/database/migrations/' . $driver;
    }

    public static function ensureMigrationsTable(PDO $pdo): void
    {
        $driver = Database::driver();
        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL UNIQUE,
                applied_at TEXT NOT NULL
            )');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    /** @return list<string> filenames applied during this call */
    public static function run(): array
    {
        $pdo = Database::connection();
        self::ensureMigrationsTable($pdo);

        $applied = [];
        $stmt = $pdo->query('SELECT filename FROM migrations');
        $already = $stmt ? array_column($stmt->fetchAll(), 'filename') : [];

        $path = self::migrationsPath();
        $files = glob($path . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $already, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Could not read migration file: {$file}");
            }

            Database::transaction(function (PDO $pdo) use ($sql, $name): void {
                foreach (self::splitStatements($sql) as $statement) {
                    $statement = trim($statement);
                    if ($statement === '') {
                        continue;
                    }
                    $pdo->exec($statement);
                }
                $insert = $pdo->prepare('INSERT INTO migrations (filename, applied_at) VALUES (:f, :a)');
                $insert->execute(['f' => $name, 'a' => date('Y-m-d H:i:s')]);
            });

            $applied[] = $name;
        }

        return $applied;
    }

    /** @return list<string> */
    private static function splitStatements(string $sql): array
    {
        // Strip "-- ..." line comments BEFORE splitting on ";" -- a
        // comment may legitimately contain a semicolon (e.g. explaining
        // "both are optional; neither is required"), and a naive split
        // would otherwise cut a statement in half there. Our migration
        // files contain no block comments, no stored routines/triggers,
        // and no string literal that itself contains a semicolon, so a
        // plain "--"-aware strip + ";" split is sufficient and safe for
        // this project's SQL specifically (this is not a general-purpose
        // SQL tokenizer).
        $withoutComments = preg_replace('/--.*$/m', '', $sql) ?? $sql;
        return array_filter(array_map('trim', explode(';', $withoutComments)), static fn (string $s) => $s !== '');
    }
}
