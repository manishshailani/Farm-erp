<?php

declare(strict_types=1);

/**
 * Test bootstrap: fresh SQLite database per test run, migrated and
 * seeded, so every run starts from a known, clean state. This is the
 * same migration/seed code path used in production (App\Core\Migrator,
 * database/seeds/*.php) -- the test suite is not testing a parallel
 * implementation of schema setup.
 */

require __DIR__ . '/../src/Core/Autoload.php';

use App\Core\Database;
use App\Core\Migrator;

$testDbPath = __DIR__ . '/../storage/test_run.sqlite';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

putenv('DB_CONNECTION=sqlite');
putenv('DB_SQLITE_PATH=' . $testDbPath);
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_SQLITE_PATH'] = $testDbPath;
putenv('APP_TIMEZONE=Asia/Kolkata');
date_default_timezone_set('Asia/Kolkata');

Database::reset();
Migrator::run();

foreach (glob(__DIR__ . '/../database/seeds/*.php') as $seedFile) {
    $seed = require $seedFile;
    if (is_callable($seed)) {
        $seed();
    }
}

function test_pdo(): \PDO
{
    return Database::connection();
}
