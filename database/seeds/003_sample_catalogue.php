<?php

declare(strict_types=1);

/**
 * A handful of sample catalogue products so the public shop is not empty
 * on a fresh install. Clearly sample/demo content -- the farm owner is
 * expected to replace these through the admin UI, not edit this file.
 */

use App\Core\Database;

return static function (): void {
    $pdo = Database::connection();
    $driver = Database::driver();
    $ignore = $driver === 'sqlite' ? 'OR IGNORE' : 'IGNORE';
    $now = date('Y-m-d H:i:s');

    $products = [
        ['SHOP-EGGS', 'Farm Fresh Eggs (dozen)', 'Free-range eggs, collected daily.', 'Eggs & Poultry', 'dozen', 90.00, 0, 50],
        ['SHOP-MILK', 'Buffalo Milk (1 litre)', 'Fresh buffalo milk.', 'Dairy', 'litre', 65.00, 0, 30],
        ['SHOP-GOAT', 'Live Goat (price on request)', 'Healthy goat available for sale; price confirmed by farm staff after enquiry.', 'Livestock', 'piece', null, 1, 1],
    ];

    foreach ($products as [$sku, $name, $desc, $category, $unit, $price, $quoteOnly, $qty]) {
        $pdo->prepare(
            "INSERT {$ignore} INTO products (sku, name, description, category, unit, price, is_quotation_only, available_quantity, min_order_quantity, is_active, created_at, updated_at)
             VALUES (:sku, :name, :desc, :cat, :unit, :price, :quote, :qty, 1, 1, :c, :u)"
        )->execute([
            'sku' => $sku, 'name' => $name, 'desc' => $desc, 'cat' => $category, 'unit' => $unit,
            'price' => $price, 'quote' => $quoteOnly, 'qty' => $qty, 'c' => $now, 'u' => $now,
        ]);
    }
};
