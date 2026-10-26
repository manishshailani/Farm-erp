<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use RuntimeException;

/**
 * Income/expense ledger and cash/bank account balances.
 *
 * "transfer" transactions move money between two of the farm's own
 * accounts and are excluded from income/expense totals everywhere in
 * this codebase (see FinancialTransaction::summary()) -- the spec
 * explicitly warns against double-counting a transfer as income.
 */
final class FinanceService
{
    public static function recordTransaction(array $data, ?int $userId): int
    {
        $type = $data['type'];
        if (!in_array($type, ['income', 'expense', 'transfer'], true)) {
            throw new RuntimeException('Invalid transaction type.');
        }
        if ($type === 'transfer' && empty($data['transfer_to_account_id'])) {
            throw new RuntimeException('Transfers require a destination account.');
        }

        return (int) Database::transaction(function ($pdo) use ($data, $userId, $type) {
            $pdo->prepare(
                'INSERT INTO financial_transactions (txn_date, type, category, enterprise, amount, account_id,
                    transfer_to_account_id, related_type, related_id, payment_method, counterparty, reference, notes,
                    is_capital_expenditure, created_by, approved_by, created_at)
                 VALUES (:txn_date, :type, :category, :enterprise, :amount, :account_id,
                    :transfer_to_account_id, :related_type, :related_id, :payment_method, :counterparty, :reference, :notes,
                    :is_capex, :created_by, :approved_by, :created_at)'
            )->execute([
                'txn_date' => $data['txn_date'],
                'type' => $type,
                'category' => $data['category'],
                'enterprise' => $data['enterprise'] ?? null,
                'amount' => (float) $data['amount'],
                'account_id' => $data['account_id'] ?? null,
                'transfer_to_account_id' => $data['transfer_to_account_id'] ?? null,
                'related_type' => $data['related_type'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'counterparty' => $data['counterparty'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_capex' => !empty($data['is_capital_expenditure']) ? 1 : 0,
                'created_by' => $userId,
                'approved_by' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $txnId = (int) Database::lastInsertId();

            if (!empty($data['account_id'])) {
                $delta = $type === 'income' ? (float) $data['amount'] : -(float) $data['amount'];
                $pdo->prepare('UPDATE accounts SET current_balance = current_balance + :d WHERE id = :id')
                    ->execute(['d' => $delta, 'id' => $data['account_id']]);
            }
            if ($type === 'transfer' && !empty($data['transfer_to_account_id'])) {
                $pdo->prepare('UPDATE accounts SET current_balance = current_balance + :a WHERE id = :id')
                    ->execute(['a' => (float) $data['amount'], 'id' => $data['transfer_to_account_id']]);
            }

            Audit::log('finance.transaction', 'financial_transaction', $txnId, null, $data);
            return $txnId;
        });
    }
}
