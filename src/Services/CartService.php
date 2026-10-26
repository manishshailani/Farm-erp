<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class CartService
{
    public static function cartForCustomer(int $customerId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM carts WHERE customer_id = :c');
        $stmt->execute(['c' => $customerId]);
        $cart = $stmt->fetch();
        if ($cart !== false) {
            return $cart;
        }

        $now = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO carts (customer_id, created_at, updated_at) VALUES (:c, :n, :n)')
            ->execute(['c' => $customerId, 'n' => $now]);
        $id = (int) Database::lastInsertId();
        return ['id' => $id, 'customer_id' => $customerId, 'created_at' => $now, 'updated_at' => $now];
    }

    /** @return list<array<string,mixed>> each row includes the joined product */
    public static function items(int $cartId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ci.*, p.name, p.price, p.unit, p.image_path, p.available_quantity, p.is_quotation_only, p.min_order_quantity
             FROM cart_items ci JOIN products p ON p.id = ci.product_id
             WHERE ci.cart_id = :c'
        );
        $stmt->execute(['c' => $cartId]);
        return $stmt->fetchAll();
    }

    public static function addItem(int $cartId, int $productId, float $quantity): void
    {
        $pdo = Database::connection();
        $product = $pdo->prepare('SELECT * FROM products WHERE id = :id AND is_active = 1');
        $product->execute(['id' => $productId]);
        $productRow = $product->fetch();
        if ($productRow === false) {
            throw new RuntimeException('Product not found or not available.');
        }

        $existing = $pdo->prepare('SELECT * FROM cart_items WHERE cart_id = :c AND product_id = :p');
        $existing->execute(['c' => $cartId, 'p' => $productId]);
        $row = $existing->fetch();

        $priceSnapshot = $productRow['is_quotation_only'] ? null : (float) $productRow['price'];

        if ($row !== false) {
            $pdo->prepare('UPDATE cart_items SET quantity = quantity + :q, unit_price_snapshot = :p WHERE id = :id')
                ->execute(['q' => $quantity, 'p' => $priceSnapshot, 'id' => $row['id']]);
        } else {
            $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, unit_price_snapshot) VALUES (:c, :p, :q, :price)')
                ->execute(['c' => $cartId, 'p' => $productId, 'q' => $quantity, 'price' => $priceSnapshot]);
        }
    }

    public static function updateQuantity(int $cartId, int $cartItemId, float $quantity): void
    {
        $pdo = Database::connection();
        if ($quantity <= 0) {
            $pdo->prepare('DELETE FROM cart_items WHERE id = :id AND cart_id = :c')->execute(['id' => $cartItemId, 'c' => $cartId]);
            return;
        }
        $pdo->prepare('UPDATE cart_items SET quantity = :q WHERE id = :id AND cart_id = :c')
            ->execute(['q' => $quantity, 'id' => $cartItemId, 'c' => $cartId]);
    }

    public static function removeItem(int $cartId, int $cartItemId): void
    {
        Database::connection()->prepare('DELETE FROM cart_items WHERE id = :id AND cart_id = :c')
            ->execute(['id' => $cartItemId, 'c' => $cartId]);
    }

    public static function clear(int $cartId): void
    {
        Database::connection()->prepare('DELETE FROM cart_items WHERE cart_id = :c')->execute(['c' => $cartId]);
    }
}
