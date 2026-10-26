<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Order extends Model
{
    protected static string $table = 'orders';

    public static function withCustomer(array $filters = [], int $limit = 200): array
    {
        $sql = 'SELECT o.*, c.name AS customer_name FROM orders o JOIN customers c ON c.id = o.customer_id WHERE 1=1';
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' AND o.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND o.customer_id = :customer_id';
            $params['customer_id'] = (int) $filters['customer_id'];
        }
        $sql .= ' ORDER BY o.id DESC LIMIT ' . (int) $limit;
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function items(int $orderId): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM order_items WHERE order_id = :o');
        $stmt->execute(['o' => $orderId]);
        return $stmt->fetchAll();
    }

    public static function statusHistory(int $orderId): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT h.*, u.name AS changed_by_name FROM order_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.order_id = :o ORDER BY h.changed_at ASC'
        );
        $stmt->execute(['o' => $orderId]);
        return $stmt->fetchAll();
    }

    public static function invoice(int $orderId): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM invoices WHERE order_id = :o');
        $stmt->execute(['o' => $orderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function findByNumber(string $orderNumber): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM orders WHERE order_number = :n');
        $stmt->execute(['n' => $orderNumber]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
