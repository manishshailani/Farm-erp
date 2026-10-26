<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use RuntimeException;

/**
 * Cart -> order -> invoice -> payment -> fulfilment, and the order status
 * state machine (spec sections 11.3-11.5 and 12).
 *
 * Stock handling: at the moment an order is PLACED, this service
 * re-validates and decrements stock inside one transaction (row-locking
 * the product/inventory row first), so two customers racing to buy the
 * last unit of the same product cannot both succeed -- the second one's
 * transaction will see the already-reduced quantity and fail cleanly with
 * an exception the controller turns into a user-facing "no longer
 * available" message. This is a "decrement-on-placement" reservation
 * policy (documented here because the spec explicitly asks for the
 * reservation policy to be documented): stock is committed to an order as
 * soon as it is placed, not held in a separate pending-reservation pool
 * and not decremented only on delivery. Cancelling an order before
 * dispatch restores the stock it consumed.
 *
 * Revenue recognition: this implementation recognises income on a CASH
 * basis -- a financial_transactions "income" row is created when a
 * payment is actually recorded (PaymentService::record()), not when the
 * order is placed or invoiced. This is a documented, configurable
 * assumption, not a claim of GAAP/accrual compliance; see
 * docs/FINANCIAL_ASSUMPTIONS.md.
 */
final class OrderService
{
    /** @var array<string, list<string>> */
    public const VALID_TRANSITIONS = [
        'draft' => ['placed', 'cancelled'],
        'awaiting_quotation_approval' => ['placed', 'cancelled'],
        'placed' => ['confirmed', 'cancelled'],
        'confirmed' => ['awaiting_payment', 'processing', 'cancelled'],
        'awaiting_payment' => ['partially_paid', 'processing', 'cancelled'],
        'partially_paid' => ['processing', 'cancelled'],
        'processing' => ['ready_for_dispatch', 'cancelled'],
        'ready_for_dispatch' => ['dispatched', 'cancelled'],
        'dispatched' => ['delivered'],
        'delivered' => ['completed', 'returned', 'refund_pending'],
        'completed' => ['refund_pending'],
        'returned' => ['refund_pending'],
        'refund_pending' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    /** Stock is restored to inventory when an order moves INTO one of these statuses. */
    private const RESTOCKING_STATUSES = ['cancelled', 'returned'];

    /**
     * Place an order from a flat list of line items (already validated by
     * the controller to belong to the given customer's cart).
     *
     * @param list<array{product_id:int, quantity:float}> $items
     */
    public static function placeOrder(
        int $customerId,
        array $items,
        string $deliveryMethod,
        ?int $deliveryAddressId,
        float $deliveryCharge,
        ?string $notes,
        ?int $userId,
    ): int {
        if ($items === []) {
            throw new RuntimeException('Cannot place an order with no items.');
        }

        return (int) Database::transaction(function ($pdo) use (
            $customerId, $items, $deliveryMethod, $deliveryAddressId, $deliveryCharge, $notes, $userId
        ) {
            $now = date('Y-m-d H:i:s');
            $subtotal = 0.0;
            $lineData = [];

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (float) $item['quantity'];
                if ($quantity <= 0) {
                    throw new RuntimeException('Quantity must be greater than zero.');
                }

                $forUpdate = Database::driver() === 'mysql' ? ' FOR UPDATE' : '';
                $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id' . $forUpdate);
                $stmt->execute(['id' => $productId]);
                $product = $stmt->fetch();
                if ($product === false || !$product['is_active']) {
                    throw new RuntimeException("Product #{$productId} is not available.");
                }

                if ($product['is_quotation_only']) {
                    $unitPrice = 0.0; // price to be set by staff on quotation approval
                } else {
                    if ((float) $quantity < (float) $product['min_order_quantity']) {
                        throw new RuntimeException("Minimum order quantity for \"{$product['name']}\" is {$product['min_order_quantity']}.");
                    }
                    self::reserveStock($pdo, $product, $quantity);
                    $unitPrice = (float) $product['price'];
                }

                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;
                $lineData[] = [
                    'product_id' => $productId,
                    'name' => $product['name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'linked_animal_id' => $product['linked_animal_id'],
                    'is_quotation_only' => (bool) $product['is_quotation_only'],
                ];
            }

            $hasQuotationItem = array_reduce($lineData, static fn ($carry, $line) => $carry || $line['is_quotation_only'], false);
            $status = $hasQuotationItem ? 'awaiting_quotation_approval' : 'placed';

            $taxAmount = 0.0; // tax configuration is out of scope for this delivery; see known limitations
            $total = $subtotal + $deliveryCharge + $taxAmount;

            $orderNumber = self::generateOrderNumber($pdo);

            $pdo->prepare(
                'INSERT INTO orders (order_number, customer_id, status, subtotal, delivery_charge, tax_amount, total_amount,
                    delivery_method, delivery_address_id, notes, created_by, placed_at, updated_at)
                 VALUES (:order_number, :customer_id, :status, :subtotal, :delivery_charge, :tax, :total,
                    :delivery_method, :address_id, :notes, :created_by, :placed_at, :updated_at)'
            )->execute([
                'order_number' => $orderNumber,
                'customer_id' => $customerId,
                'status' => $status,
                'subtotal' => $subtotal,
                'delivery_charge' => $deliveryCharge,
                'tax' => $taxAmount,
                'total' => $total,
                'delivery_method' => $deliveryMethod,
                'address_id' => $deliveryAddressId,
                'notes' => $notes,
                'created_by' => $userId,
                'placed_at' => $now,
                'updated_at' => $now,
            ]);
            $orderId = (int) Database::lastInsertId();

            foreach ($lineData as $line) {
                $pdo->prepare(
                    'INSERT INTO order_items (order_id, product_id, product_name_snapshot, quantity, unit_price, line_total, linked_animal_id)
                     VALUES (:order_id, :product_id, :name, :qty, :price, :total, :animal_id)'
                )->execute([
                    'order_id' => $orderId,
                    'product_id' => $line['product_id'],
                    'name' => $line['name'],
                    'qty' => $line['quantity'],
                    'price' => $line['unit_price'],
                    'total' => $line['line_total'],
                    'animal_id' => $line['linked_animal_id'],
                ]);

                if ($line['linked_animal_id'] !== null) {
                    $pdo->prepare("UPDATE animals SET status = 'reserved', updated_at = :u WHERE id = :id AND status = 'active'")
                        ->execute(['u' => $now, 'id' => $line['linked_animal_id']]);
                }
            }

            self::writeStatusHistory($pdo, $orderId, $status, $userId, 'Order placed by customer.');

            if (!$hasQuotationItem) {
                self::createInvoice($pdo, $orderId, $customerId, $total);
            }

            Audit::log('order.place', 'order', $orderId, null, ['order_number' => $orderNumber, 'total' => $total]);

            return $orderId;
        });
    }

    private static function reserveStock(\PDO $pdo, array $product, float $quantity): void
    {
        if ($product['linked_inventory_item_id'] !== null) {
            InventoryService::consumeStock(
                (int) $product['linked_inventory_item_id'],
                $quantity,
                'order',
                null, // order id not yet known; linked via stock_movements notes instead
                null,
                'Reserved for customer order (product #' . $product['id'] . ')'
            );
            return;
        }

        if ($product['linked_animal_id'] !== null) {
            return; // single-animal listings are reserved via animals.status, not a quantity.
        }

        $available = (float) $product['available_quantity'];
        if ($available < $quantity) {
            throw new RuntimeException("Insufficient stock for \"{$product['name']}\": requested {$quantity}, available {$available}.");
        }
        $pdo->prepare('UPDATE products SET available_quantity = available_quantity - :q, updated_at = :u WHERE id = :id')
            ->execute(['q' => $quantity, 'u' => date('Y-m-d H:i:s'), 'id' => $product['id']]);
    }

    private static function restoreStock(\PDO $pdo, array $orderItem): void
    {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute(['id' => $orderItem['product_id']]);
        $product = $stmt->fetch();
        if ($product === false) {
            return;
        }

        if ($product['linked_inventory_item_id'] !== null) {
            InventoryService::receiveStock(
                (int) $product['linked_inventory_item_id'],
                (float) $orderItem['quantity'],
                0.0,
                date('Y-m-d'),
                null,
                'Order cancellation/return restock',
                null,
                null
            );
            return;
        }

        if ($orderItem['linked_animal_id'] !== null) {
            $pdo->prepare("UPDATE animals SET status = 'active', updated_at = :u WHERE id = :id AND status = 'reserved'")
                ->execute(['u' => date('Y-m-d H:i:s'), 'id' => $orderItem['linked_animal_id']]);
            return;
        }

        $pdo->prepare('UPDATE products SET available_quantity = available_quantity + :q, updated_at = :u WHERE id = :id')
            ->execute(['q' => $orderItem['quantity'], 'u' => date('Y-m-d H:i:s'), 'id' => $orderItem['product_id']]);
    }

    public static function transition(int $orderId, string $newStatus, ?int $userId, ?string $notes = null): void
    {
        Database::transaction(function ($pdo) use ($orderId, $newStatus, $userId, $notes) {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
            $stmt->execute(['id' => $orderId]);
            $order = $stmt->fetch();
            if ($order === false) {
                throw new RuntimeException("Order #{$orderId} not found.");
            }

            $current = $order['status'];
            $allowed = self::VALID_TRANSITIONS[$current] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                throw new RuntimeException("Cannot move order from \"{$current}\" to \"{$newStatus}\".");
            }

            if (in_array($newStatus, self::RESTOCKING_STATUSES, true)) {
                $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :o');
                $itemsStmt->execute(['o' => $orderId]);
                foreach ($itemsStmt->fetchAll() as $orderItem) {
                    self::restoreStock($pdo, $orderItem);
                }
            }

            if ($newStatus === 'confirmed' && $current === 'awaiting_quotation_approval') {
                // Quotation approved: an invoice did not exist yet (price
                // was 0 at placement); staff must have updated order_items
                // prices via updateQuotedPrice() before confirming.
                $totalStmt = $pdo->prepare('SELECT COALESCE(SUM(line_total),0) AS t FROM order_items WHERE order_id = :o');
                $totalStmt->execute(['o' => $orderId]);
                $total = (float) $totalStmt->fetch()['t'] + (float) $order['delivery_charge'] + (float) $order['tax_amount'];
                $pdo->prepare('UPDATE orders SET subtotal = :s, total_amount = :t WHERE id = :id')
                    ->execute(['s' => $total - (float) $order['delivery_charge'] - (float) $order['tax_amount'], 't' => $total, 'id' => $orderId]);
                self::createInvoice($pdo, $orderId, (int) $order['customer_id'], $total);
            }

            $pdo->prepare('UPDATE orders SET status = :s, updated_at = :u WHERE id = :id')
                ->execute(['s' => $newStatus, 'u' => date('Y-m-d H:i:s'), 'id' => $orderId]);

            self::writeStatusHistory($pdo, $orderId, $newStatus, $userId, $notes);

            if (in_array($newStatus, ['delivered', 'completed'], true)) {
                $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :o AND linked_animal_id IS NOT NULL');
                $itemsStmt->execute(['o' => $orderId]);
                foreach ($itemsStmt->fetchAll() as $orderItem) {
                    $pdo->prepare("UPDATE animals SET status = 'sold', sold_at = :u, updated_at = :u WHERE id = :id")
                        ->execute(['u' => date('Y-m-d H:i:s'), 'id' => $orderItem['linked_animal_id']]);
                }
            }

            if ($newStatus === 'refunded') {
                $invoiceStmt = $pdo->prepare('SELECT * FROM invoices WHERE order_id = :o');
                $invoiceStmt->execute(['o' => $orderId]);
                $invoice = $invoiceStmt->fetch();
                if ($invoice !== false) {
                    FinanceService::recordTransaction([
                        'txn_date' => date('Y-m-d'),
                        'type' => 'expense',
                        'category' => 'Sales Refund',
                        'enterprise' => 'general',
                        'amount' => $invoice['amount_paid'],
                        'related_type' => 'order',
                        'related_id' => $orderId,
                        'notes' => "Refund for order {$order['order_number']}",
                    ], $userId);
                }
            }

            Audit::log('order.transition', 'order', $orderId, ['status' => $current], ['status' => $newStatus, 'notes' => $notes]);
        });
    }

    private static function createInvoice(\PDO $pdo, int $orderId, int $customerId, float $amount): void
    {
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
        $pdo->prepare(
            'INSERT INTO invoices (invoice_number, order_id, customer_id, amount, amount_paid, status, issued_at)
             VALUES (:n, :o, :c, :a, 0, "unpaid", :issued_at)'
        )->execute([
            'n' => $invoiceNumber,
            'o' => $orderId,
            'c' => $customerId,
            'a' => $amount,
            'issued_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function writeStatusHistory(\PDO $pdo, int $orderId, string $status, ?int $userId, ?string $notes): void
    {
        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, status, changed_by, changed_at, notes) VALUES (:o, :s, :u, :n, :notes)'
        )->execute(['o' => $orderId, 's' => $status, 'u' => $userId, 'n' => date('Y-m-d H:i:s'), 'notes' => $notes]);
    }

    private static function generateOrderNumber(\PDO $pdo): string
    {
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM orders');
        $seq = ((int) $stmt->fetch()['c']) + 1;
        return 'ORD-' . date('Ymd') . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
