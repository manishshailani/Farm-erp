<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO connection factory + thin query helpers.
 *
 * Supports two drivers:
 *  - "mysql": the production target (MySQL 8 / MariaDB, run under XAMPP).
 *  - "sqlite": used ONLY when a MySQL server is not reachable, e.g. the
 *    sandbox this project was first developed in had no MySQL/MariaDB
 *    package available (no apt/docker registry access). The automated
 *    test suite runs against sqlite there. Business logic that is
 *    sensitive to engine differences (transactions, foreign keys,
 *    autoincrement ids, DECIMAL handling) has been kept inside the engine
 *    behaviour both drivers share; see docs/DATABASE_PORTABILITY.md.
 *
 * All queries in this codebase use PDO prepared statements with bound
 * parameters -- never string-interpolated SQL -- to prevent SQL injection.
 */
final class Database
{
    private static ?PDO $instance = null;
    private static string $driver = 'mysql';

    public static function connection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = (string) Env::get('DB_CONNECTION', 'mysql');
        self::$driver = $driver;

        try {
            if ($driver === 'sqlite') {
                $path = (string) Env::get('DB_SQLITE_PATH', 'storage/database.sqlite');
                if (!str_starts_with($path, '/')) {
                    $path = dirname(__DIR__, 2) . '/' . $path;
                }
                $dsn = 'sqlite:' . $path;
                $pdo = new PDO($dsn);
                $pdo->exec('PRAGMA foreign_keys = ON;');
                $pdo->exec('PRAGMA journal_mode = WAL;');
            } else {
                $host = (string) Env::get('DB_HOST', '127.0.0.1');
                $port = (string) Env::get('DB_PORT', '3306');
                $db = (string) Env::get('DB_DATABASE', 'farm_erp');
                $charset = 'utf8mb4';
                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
                $pdo = new PDO(
                    $dsn,
                    (string) Env::get('DB_USERNAME', 'root'),
                    (string) Env::get('DB_PASSWORD', ''),
                    [
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
                    ]
                );
            }
        } catch (PDOException $e) {
            // Never leak DSN/credentials to the browser; detailed error goes
            // only to the server log.
            Logger::error('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. See server logs for details.');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        self::$instance = $pdo;
        return $pdo;
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    /** Allows tests to force a fresh connection against a different DSN. */
    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    /**
     * Run $callback inside a transaction. Rolls back and rethrows on any
     * exception. Nested calls reuse the outer transaction (PDO does not
     * support real nested transactions; we guard with a depth counter so
     * service methods can call each other without double-BEGIN errors).
     */
    private static int $transactionDepth = 0;

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $isOutermost = self::$transactionDepth === 0;

        if ($isOutermost) {
            $pdo->beginTransaction();
        }
        self::$transactionDepth++;

        try {
            $result = $callback($pdo);
            self::$transactionDepth--;
            if ($isOutermost) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            self::$transactionDepth--;
            if ($isOutermost && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
