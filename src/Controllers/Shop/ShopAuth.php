<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Redirect;
use App\Core\Request;

/**
 * Customer-portal authentication helpers.
 *
 * The public site shares the same `users` table and login mechanism as
 * the staff application (Auth::class) -- a customer is simply a user
 * whose account has a matching row in `customers` (linked via
 * customers.user_id) and the "Customer" role, rather than a second,
 * separate auth system. currentCustomer() is what prevents Customer A
 * from ever being able to view Customer B's data: every shop controller
 * resolves "the customer" from the authenticated session's own user_id,
 * never from a request parameter, so there is no id a customer could
 * tamper with in the URL to reach someone else's cart/orders/addresses.
 */
final class ShopAuth
{
    /** @return array<string,mixed>|null */
    public static function currentCustomer(): ?array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM customers WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function middleware(): callable
    {
        return static function (Request $request, array $args, callable $next): void {
            if (self::currentCustomer() === null) {
                Redirect::to('/shop/login');
                return;
            }
            $next();
        };
    }
}
