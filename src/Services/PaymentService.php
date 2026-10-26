<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use RuntimeException;

/**
 * Records a payment (or refund, via $isRefund) against an invoice, keeps
 * invoices.amount_paid/status in sync, and -- for a non-refund payment --
 * creates the corresponding "income" financial_transactions row in the
 * same database transaction. This is the one and only place income is
 * recognised for a sale (see OrderService's class docblock on cash-basis
 * recognition), so a payment can never be recorded without the ledger
 * entry it implies, or vice versa.
 */
final class PaymentService
{
    public static function record(
        int $invoiceId,
        float $amount,
        string $method,
        ?string $reference,
        ?string $notes,
        bool $isRefund,
        ?int $userId,
    ): int {
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        return (int) Database::transaction(function ($pdo) use ($invoiceId, $amount, $method, $reference, $notes, $isRefund, $userId) {
            $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id');
            $stmt->execute(['id' => $invoiceId]);
            $invoice = $stmt->fetch();
            if ($invoice === false) {
                throw new RuntimeException("Invoice #{$invoiceId} not found.");
            }

            $newPaid = (float) $invoice['amount_paid'] + ($isRefund ? -$amount : $amount);
            if ($newPaid < 0) {
                throw new RuntimeException('Refund amount exceeds amount already paid on this invoice.');
            }
            if (!$isRefund && $newPaid > (float) $invoice['amount'] + 0.01) {
                throw new RuntimeException('Payment amount exceeds the outstanding invoice balance.');
            }

            $status = 'unpaid';
            if ($newPaid >= (float) $invoice['amount'] - 0.01) {
                $status = 'paid';
            } elseif ($newPaid > 0) {
                $status = 'partially_paid';
            }

            $pdo->prepare('UPDATE invoices SET amount_paid = :p, status = :s WHERE id = :id')
                ->execute(['p' => $newPaid, 's' => $status, 'id' => $invoiceId]);

            $pdo->prepare(
                'INSERT INTO payments (invoice_id, amount, method, reference, is_refund, received_at, recorded_by, notes)
                 VALUES (:invoice_id, :amount, :method, :reference, :is_refund, :received_at, :recorded_by, :notes)'
            )->execute([
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'is_refund' => $isRefund ? 1 : 0,
                'received_at' => date('Y-m-d H:i:s'),
                'recorded_by' => $userId,
                'notes' => $notes,
            ]);
            $paymentId = (int) Database::lastInsertId();

            if (!$isRefund) {
                FinanceService::recordTransaction([
                    'txn_date' => date('Y-m-d'),
                    'type' => 'income',
                    'category' => 'Sales Receipt',
                    'enterprise' => 'general',
                    'amount' => $amount,
                    'related_type' => 'order',
                    'related_id' => $invoice['order_id'],
                    'payment_method' => $method,
                    'reference' => $reference,
                    'notes' => "Payment for invoice {$invoice['invoice_number']}",
                ], $userId);
            }

            // Order status convenience transition: confirmed/awaiting_payment -> partially_paid/processing.
            $orderStmt = $pdo->prepare('SELECT status FROM orders WHERE id = :id');
            $orderStmt->execute(['id' => $invoice['order_id']]);
            $orderStatus = $orderStmt->fetch();
            if ($orderStatus !== false && !$isRefund) {
                $current = $orderStatus['status'];
                if ($status === 'partially_paid' && in_array($current, ['confirmed', 'awaiting_payment'], true)) {
                    OrderService::transition((int) $invoice['order_id'], 'partially_paid', $userId, 'Partial payment received.');
                } elseif ($status === 'paid' && in_array($current, ['confirmed', 'awaiting_payment', 'partially_paid'], true)) {
                    OrderService::transition((int) $invoice['order_id'], 'processing', $userId, 'Payment received in full.');
                }
            }

            Audit::log($isRefund ? 'payment.refund' : 'payment.record', 'invoice', $invoiceId, null, [
                'amount' => $amount, 'method' => $method,
            ]);

            return $paymentId;
        });
    }
}
