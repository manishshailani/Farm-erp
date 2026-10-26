<?php

declare(strict_types=1);

/**
 * Single front controller. Every request is routed through here (see
 * the .htaccess in this same directory for the Apache rewrite rule that
 * makes that true under XAMPP).
 */

require __DIR__ . '/../src/Core/Autoload.php';

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;

Env::load(__DIR__ . '/../.env');

$debug = (bool) Env::get('APP_DEBUG', false);
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED);
ini_set('display_errors', $debug ? '1' : '0');

date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Asia/Kolkata'));

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    Logger::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        // Never leak stack traces / file paths / query text to the
        // customer-facing site -- generic message only, detail goes to
        // storage/logs/app.log.
        View::render('errors/500', [], '');
    }
});

$request = Request::fromGlobals();
Session::start($request->isHttps() && (bool) Env::get('SESSION_SECURE_COOKIE', false));

// Expose csrf_field()/e()/money()/fdate() as plain functions for views,
// which are plain PHP templates (see View::render) rather than a
// templating DSL.
if (!function_exists('csrf_field')) {
    function csrf_field(): string { return Csrf::field(); }
}
if (!function_exists('e')) {
    function e(mixed $v): string { return View::e($v); }
}
if (!function_exists('money')) {
    function money(float|string $v): string { return View::money($v); }
}
if (!function_exists('fdate')) {
    function fdate(?string $v, string $f = 'd-m-Y'): string { return View::date($v, $f); }
}

require __DIR__ . '/../src/routes.php';
