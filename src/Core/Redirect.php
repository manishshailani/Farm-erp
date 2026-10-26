<?php

declare(strict_types=1);

namespace App\Core;

/** Centralizes the "redirect to an app-relative path" logic used by every controller. */
final class Redirect
{
    public static function to(string $path): void
    {
        header('Location: ' . self::basePath() . $path);
    }

    public static function basePath(): string
    {
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        return $scriptDir === '/' ? '' : $scriptDir;
    }
}
