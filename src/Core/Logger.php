<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal file logger. Writes to storage/logs/app.log.
 *
 * This intentionally never throws on its own failure (a logging failure
 * must not take down the request); it fails silently to error_log() as a
 * last resort, which PHP itself guarantees is always available.
 */
final class Logger
{
    private static function path(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs/app.log';
    }

    private static function write(string $level, string $message): void
    {
        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), $level, $message, PHP_EOL);
        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $written = @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log($line);
        }
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function warning(string $message): void
    {
        self::write('WARNING', $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }
}
