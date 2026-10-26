<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Invoice extends Model
{
    protected static string $table = 'invoices';

    public static function payments(int $invoiceId): array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM payments WHERE invoice_id = :i ORDER BY received_at');
        $stmt->execute(['i' => $invoiceId]);
        return $stmt->fetchAll();
    }
}
