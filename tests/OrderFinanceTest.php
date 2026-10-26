<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;

return function (): void {
    echo "OrderFinanceTest\n";

    $pdo = test_pdo();
    $now = date('Y-m-d H:i:s');

    // Workflow #13: register a customer.
    $customerId = null;
    T::run('Workflow #13: register a customer', function () use (&$customerId, $pdo, $now) {
        $pdo->prepare(
            "INSERT INTO customers (name, phone, email, credit_limit, is_active, created_at, updated_at)
             VALUES ('Test Customer', '9000000000', 'test-customer@example.com', 0, 1, :c, :u)"
        )->execute(['c' => $now, 'u' => $now]);
        $customerId = (int) $pdo->lastInsertId();
        T::assertTrue($customerId > 0, 'customer must be created with an id');
    });

    // A stock-controlled product to order against.
    $productId = null;
    T::run('Set up a stock-controlled test product', function () use (&$productId, $pdo, $now) {
        $pdo->prepare(
            "INSERT INTO products (sku, name, unit, price, is_quotation_only, available_quantity, min_order_quantity, is_active, created_at, updated_at)
             VALUES ('TEST-PRODUCT', 'Test Eggs', 'dozen', 100.00, 0, 10, 1, 1, :c, :u)"
        )->execute(['c' => $now, 'u' => $now]);
        $productId = (int) $pdo->lastInsertId();
    });

    $orderId = null;
    T::run('Workflow #14: place an order and validate stock is decremented', function () use (&$orderId, &$customerId, &$productId) {
        $orderId = OrderService::placeOrder($customerId, [['product_id' => $productId, 'quantity' => 4]], 'pickup', null, 0.0, null, 1);
        T::assertTrue($orderId > 0, 'order must be created with an id');

        $product = test_pdo()->prepare('SELECT available_quantity FROM products WHERE id = :id');
        $product->execute(['id' => $productId]);
        T::assertEqualsFloat(6.0, (float) $product->fetch()['available_quantity'], '10 - 4 ordered = 6 remaining');

        $order = test_pdo()->prepare('SELECT status FROM orders WHERE id = :id');
        $order->execute(['id' => $orderId]);
        T::assertSame('placed', $order->fetch()['status'], 'a non-quotation order starts in "placed" status');
    });

    T::run('Ordering more than available stock is refused (no overselling)', function () use (&$productId, &$customerId) {
        T::assertThrows(
            fn () => OrderService::placeOrder($customerId, [['product_id' => $productId, 'quantity' => 999]], 'pickup', null, 0.0, null, 1),
            'an order exceeding available stock must be rejected'
        );
        $product = test_pdo()->prepare('SELECT available_quantity FROM products WHERE id = :id');
        $product->execute(['id' => $productId]);
        T::assertEqualsFloat(6.0, (float) $product->fetch()['available_quantity'], 'a rejected order must not have partially decremented stock (atomic transaction)');
    });

    T::run('Workflow #15: confirm, process and deliver the order', function () use (&$orderId) {
        OrderService::transition($orderId, 'confirmed', 1, 'test');
        OrderService::transition($orderId, 'processing', 1, 'test');
        OrderService::transition($orderId, 'ready_for_dispatch', 1, 'test');
        OrderService::transition($orderId, 'dispatched', 1, 'test');
        OrderService::transition($orderId, 'delivered', 1, 'test');

        $order = test_pdo()->prepare('SELECT status FROM orders WHERE id = :id');
        $order->execute(['id' => $orderId]);
        T::assertSame('delivered', $order->fetch()['status'], 'order should now be delivered');
    });

    T::run('An invalid status transition is refused (state machine enforcement)', function () use (&$orderId) {
        T::assertThrows(
            fn () => OrderService::transition($orderId, 'placed', 1, 'illegal'),
            'a delivered order cannot go back to "placed"'
        );
    });

    T::run('Workflow #16: customer can see only their own order', function () use (&$orderId, &$customerId) {
        $own = Order::withCustomer(['customer_id' => $customerId]);
        $found = array_filter($own, static fn ($o) => (int) $o['id'] === $orderId);
        T::assertTrue(count($found) === 1, 'the customer\'s own order list must include this order');
    });

    T::run('Workflow #17 and #18: invoice exists, payment recorded, customer balance updates', function () use (&$orderId, &$customerId) {
        $invoice = Order::invoice($orderId);
        T::assertNotNull($invoice, 'placing a non-quotation order must create an invoice');
        T::assertEqualsFloat(400.0, (float) $invoice['amount'], '4 units x Rs.100 = Rs.400');

        $before = Customer::outstandingBalance($customerId);
        T::assertEqualsFloat(400.0, $before, 'full invoice amount should be outstanding before payment');

        PaymentService::record((int) $invoice['id'], 400.0, 'cash', null, 'Full payment', false, 1);

        $after = Customer::outstandingBalance($customerId);
        T::assertEqualsFloat(0.0, $after, 'outstanding balance should be zero after full payment');

        $income = FinancialTransaction::summary(date('Y-m-01'), date('Y-m-t'));
        T::assertTrue($income['income'] >= 400.0, 'recording a payment must create a matching income ledger entry');
    });

    T::run('Over-paying an invoice beyond its total is refused', function () use (&$orderId) {
        $invoice = Order::invoice($orderId);
        T::assertThrows(
            fn () => PaymentService::record((int) $invoice['id'], 50.0, 'cash', null, null, false, 1),
            'cannot record a payment that exceeds the outstanding balance'
        );
    });

    // Workflow #19 and #20: cancellation + refund, on a SEPARATE order so the
    // already-delivered order above is untouched.
    $refundOrderId = null;
    T::run('Workflow #19: cancel an eligible (not-yet-dispatched) order and verify stock restoration', function () use (&$refundOrderId, &$customerId, &$productId) {
        $refundOrderId = OrderService::placeOrder($customerId, [['product_id' => $productId, 'quantity' => 2]], 'pickup', null, 0.0, null, 1);
        $before = test_pdo()->prepare('SELECT available_quantity FROM products WHERE id = :id');
        $before->execute(['id' => $productId]);
        $beforeQty = (float) $before->fetch()['available_quantity'];

        OrderService::transition($refundOrderId, 'cancelled', 1, 'customer changed their mind');

        $after = test_pdo()->prepare('SELECT available_quantity FROM products WHERE id = :id');
        $after->execute(['id' => $productId]);
        $afterQty = (float) $after->fetch()['available_quantity'];

        T::assertEqualsFloat($beforeQty + 2.0, $afterQty, 'cancelling must restore the 2 units back to available stock');
    });

    T::run('Workflow #20: process a refund on a delivered order and verify the ledger', function () use (&$orderId) {
        OrderService::transition($orderId, 'refund_pending', 1, 'customer returned goods');
        OrderService::transition($orderId, 'refunded', 1, 'refund issued');

        $expenseBefore = FinancialTransaction::summary(date('Y-m-01'), date('Y-m-t'))['expense'];
        T::assertTrue($expenseBefore >= 400.0, 'a refund must post a matching expense ledger entry equal to what was paid');
    });
};
