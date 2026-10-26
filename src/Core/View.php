<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny PHP-template view renderer (no templating DSL/dependency).
 *
 * Deliberately not a "smart" template engine: every value passed to a view
 * must be escaped by the view itself via e(), which keeps the
 * escaping decision visible at the point of output -- the standard
 * defence against XSS when you are not using an engine with
 * autoescaping built in.
 */
final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $viewPath = self::path($view);
        if (!is_file($viewPath)) {
            http_response_code(500);
            echo 'View not found: ' . self::e($view);
            return;
        }

        $content = self::capture($viewPath, $data);

        if ($layout !== '') {
            $layoutPath = self::path($layout);
            if (is_file($layoutPath)) {
                echo self::capture($layoutPath, array_merge($data, ['content' => $content]));
                return;
            }
        }

        echo $content;
    }

    /** @param array<string,mixed> $data */
    private static function capture(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }

    private static function path(string $view): string
    {
        return dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';
    }

    /** HTML-escape helper for use inside view templates. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function money(float|string $amount): string
    {
        return '₹' . number_format((float) $amount, 2);
    }

    public static function date(?string $value, string $format = 'd-m-Y'): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        $ts = strtotime($value);
        return $ts === false ? self::e($value) : date($format, $ts);
    }
}
