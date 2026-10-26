<?php

declare(strict_types=1);

/**
 * PSR-4-style autoloader for the "App\" namespace, mapped to /src.
 *
 * Why a hand-written autoloader instead of Composer's? This project was
 * first built in a sandboxed environment with no network access to
 * Packagist, so `composer install` could not run. A single-namespace
 * autoloader needs no third-party code and is a correct, if minimal,
 * substitute for Composer's PSR-4 autoloader for this project's own
 * classes. On a machine with normal internet access, run:
 *
 *   composer dump-autoload -o
 *
 * and switch public/index.php to require vendor/autoload.php instead; the
 * namespace-to-path mapping below matches what a "App\\": "src/" PSR-4
 * entry in composer.json would produce, so no class needs to move.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
