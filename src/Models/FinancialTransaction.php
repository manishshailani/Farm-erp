<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class FinancialTransaction extends Model
{
    protected static string $table = 'financial_transactions';

    public static function between(string $from, string $to): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT * FROM financial_transactions WHERE txn_date BETWEEN :f AND :t ORDER BY txn_date, id'
        );
        $stmt->execute(['f' => $from, 't' => $to]);
        return $stmt->fetchAll();
    }

    /** @return array{income: float, expense: float, net: float} */
    public static function summary(string $from, string $to): array
    {
        $stmt = self::pdo()->prepare(
            "SELECT type, COALESCE(SUM(amount),0) AS total FROM financial_transactions
             WHERE txn_date BETWEEN :f AND :t AND type != 'transfer' GROUP BY type"
        );
        $stmt->execute(['f' => $from, 't' => $to]);
        $income = 0.0;
        $expense = 0.0;
        foreach ($stmt->fetchAll() as $row) {
            if ($row['type'] === 'income') {
                $income = (float) $row['total'];
            } elseif ($row['type'] === 'expense') {
                $expense = (float) $row['total'];
            }
        }
        return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense];
    }
}
