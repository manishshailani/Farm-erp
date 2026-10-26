#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Command-line administration tool for the Farm ERP.
 *
 * Usage:
 *   php bin/console.php migrate
 *   php bin/console.php seed
 *   php bin/console.php create-admin
 *   php bin/console.php create-admin --name="Jane Doe" --email=jane@example.com --password="..." --non-interactive
 *   php bin/console.php reset-demo-data --i-understand-this-deletes-all-data
 */

require __DIR__ . '/../src/Core/Autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;

Env::load(__DIR__ . '/../.env');
date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Asia/Kolkata'));

$command = $argv[1] ?? '';

function cli_option(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return substr($arg, strlen("--{$name}="));
        }
    }
    return $default;
}

function cli_flag(array $argv, string $name): bool
{
    return in_array("--{$name}", $argv, true);
}

switch ($command) {
    case 'migrate':
        $applied = Migrator::run();
        if ($applied === []) {
            echo "Nothing to migrate -- database is already up to date.\n";
        } else {
            echo "Applied migrations:\n";
            foreach ($applied as $file) {
                echo "  - {$file}\n";
            }
        }
        break;

    case 'seed':
        $pdo = Database::connection();
        $seedFiles = glob(__DIR__ . '/../database/seeds/*.php') ?: [];
        sort($seedFiles);
        foreach ($seedFiles as $file) {
            echo 'Seeding: ' . basename($file) . "\n";
            $seed = require $file;
            if (is_callable($seed)) {
                $seed();
            }
        }
        echo "Seeding complete.\n";
        break;

    case 'create-admin':
        $nonInteractive = cli_flag($argv, 'non-interactive');
        $name = cli_option($argv, 'name');
        $email = cli_option($argv, 'email');
        $password = cli_option($argv, 'password');

        if (!$nonInteractive) {
            $name ??= readline('Full name: ') ?: null;
            $email ??= readline('Email: ') ?: null;
            if ($password === null) {
                // readline() does not support masked input portably across
                // platforms without an external dependency; the password is
                // only echoed to the operator's own terminal, which is an
                // accepted trade-off for a local admin-creation CLI tool.
                $password = readline('Password (min 10 characters): ') ?: null;
            }
        }

        if (!$name || !$email || !$password) {
            fwrite(STDERR, "Name, email and password are all required.\n");
            exit(1);
        }
        if (strlen($password) < 10) {
            fwrite(STDERR, "Password must be at least 10 characters.\n");
            exit(1);
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            fwrite(STDERR, "Email address is not valid.\n");
            exit(1);
        }

        $pdo = Database::connection();
        $existing = $pdo->prepare('SELECT id FROM users WHERE email = :e');
        $existing->execute(['e' => $email]);
        if ($existing->fetchColumn() !== false) {
            fwrite(STDERR, "A user with that email already exists.\n");
            exit(1);
        }

        $roleRow = $pdo->query("SELECT id FROM roles WHERE name = 'Super Administrator'")->fetch();
        if ($roleRow === false) {
            fwrite(STDERR, "Role 'Super Administrator' not found -- run 'php bin/console.php seed' first.\n");
            exit(1);
        }

        $now = date('Y-m-d H:i:s');
        Database::transaction(function ($pdo) use ($name, $email, $password, $roleRow, $now) {
            $insert = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at)
                 VALUES (:n, :e, :p, 1, :c, :u)'
            );
            $insert->execute([
                'n' => $name,
                'e' => $email,
                'p' => password_hash($password, PASSWORD_BCRYPT),
                'c' => $now,
                'u' => $now,
            ]);
            $userId = (int) Database::lastInsertId();
            $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)')
                ->execute(['u' => $userId, 'r' => $roleRow['id']]);
        });

        echo "Administrator account created for {$email}.\n";
        echo "Sign in at the application's /login page with this email and the password you entered.\n";
        break;

    case 'reset-demo-data':
        if (!cli_flag($argv, 'i-understand-this-deletes-all-data')) {
            fwrite(STDERR, "Refusing to run: this command deletes ALL application data.\n");
            fwrite(STDERR, "Re-run with --i-understand-this-deletes-all-data to proceed.\n");
            fwrite(STDERR, "This is intended for a fresh development/demo database only -- never run it against production.\n");
            exit(1);
        }
        if ((string) Env::get('APP_ENV', 'production') === 'production') {
            fwrite(STDERR, "Refusing to run: APP_ENV is 'production'. Change APP_ENV in .env to run this on a non-production copy only.\n");
            exit(1);
        }

        $pdo = Database::connection();
        $driver = Database::driver();
        $tables = $pdo->query(
            $driver === 'sqlite'
                ? "SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('migrations')"
                : "SHOW TABLES"
        )->fetchAll(\PDO::FETCH_COLUMN);

        Database::transaction(function ($pdo) use ($tables, $driver) {
            if ($driver === 'sqlite') {
                $pdo->exec('PRAGMA foreign_keys = OFF');
            } else {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            }
            foreach ($tables as $table) {
                if ($table === 'migrations') {
                    continue;
                }
                $pdo->exec("DELETE FROM {$table}");
            }
            if ($driver === 'sqlite') {
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        });
        echo "All application data deleted. Run 'php bin/console.php seed' and 'create-admin' again.\n";
        break;

    default:
        echo "Farm ERP console\n\n";
        echo "Available commands:\n";
        echo "  migrate                Run pending database migrations\n";
        echo "  seed                   Load roles/permissions/reference data (idempotent)\n";
        echo "  create-admin           Create the first administrator account\n";
        echo "  reset-demo-data        Wipe all data on a NON-production database (requires confirmation flag)\n";
        exit($command === '' ? 0 : 1);
}
