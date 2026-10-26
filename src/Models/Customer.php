<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Customer extends Model
{
    protected static string $table = 'customers';

    public static function findByUserId(int $userId): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM customers WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function addresses(int $customerId): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM customer_addresses WHERE customer_id = :c ORDER BY is_default DESC, id');
        $stmt->execute(['c' => $customerId]);
        return $stmt->fetchAll();
    }

    /** Receivables ledger: invoices and payments for this customer. */
    public static function statement(int $customerId): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT i.id, i.invoice_number, i.amount, i.amount_paid, i.status, i.issued_at, o.order_number
             FROM invoices i JOIN orders o ON o.id = i.order_id
             WHERE i.customer_id = :c ORDER BY i.issued_at DESC'
        );
        $stmt->execute(['c' => $customerId]);
        return $stmt->fetchAll();
    }

    public static function outstandingBalance(int $customerId): float
    {
        $stmt = self::pdo()->prepare(
            "SELECT COALESCE(SUM(amount - amount_paid), 0) AS bal FROM invoices WHERE customer_id = :c AND status != 'void'"
        );
        $stmt->execute(['c' => $customerId]);
        return (float) $stmt->fetch()['bal'];
    }
}
