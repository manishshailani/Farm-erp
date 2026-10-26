<?php

declare(strict_types=1);

use App\Core\Audit;
use App\Core\Csrf;
use App\Models\AuditLog;
use App\Models\Order;

return function (): void {
    echo "SecurityTest\n";

    $pdo = test_pdo();
    $now = date('Y-m-d H:i:s');

    T::run('Workflow #21: a customer query scoped by customer_id never returns another customer\'s orders', function () use ($pdo, $now) {
        $pdo->prepare("INSERT INTO customers (name, is_active, created_at, updated_at) VALUES ('Customer A', 1, :c, :u)")->execute(['c' => $now, 'u' => $now]);
        $customerA = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO customers (name, is_active, created_at, updated_at) VALUES ('Customer B', 1, :c, :u)")->execute(['c' => $now, 'u' => $now]);
        $customerB = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO orders (order_number, customer_id, status, subtotal, delivery_charge, tax_amount, total_amount, delivery_method, placed_at, updated_at)
             VALUES ('ORD-SEC-TEST-1', :c, 'placed', 10, 0, 0, 10, 'pickup', :p, :u)"
        )->execute(['c' => $customerA, 'p' => $now, 'u' => $now]);

        $customerBOrders = Order::withCustomer(['customer_id' => $customerB]);
        T::assertSame(0, count($customerBOrders), 'Customer B must see zero orders that belong to Customer A');

        $customerAOrders = Order::withCustomer(['customer_id' => $customerA]);
        T::assertSame(1, count($customerAOrders), 'Customer A must see exactly their own order');
    });

    T::run('CSRF token verification rejects a wrong/missing token and accepts the real one', function () {
        // Csrf::token() lazily creates a per-session token; outside an HTTP
        // request there is no session superglobal populated, so we seed it
        // directly the same way Session::start() would.
        $_SESSION['_csrf_token'] = 'the-real-token';
        T::assertTrue(!Csrf::verify('a-forged-token'), 'a forged CSRF token must be rejected');
        T::assertTrue(!Csrf::verify(''), 'an empty/missing CSRF token must be rejected');
        T::assertTrue(Csrf::verify('the-real-token'), 'the genuine session token must be accepted');
    });

    T::run('Audit log records who/what/when for a sensitive action', function () {
        // Exercised indirectly throughout the suite (every Service call
        // above writes an audit_logs row); here we assert directly that
        // the table is actually being populated end-to-end, not just that
        // Audit::log() compiles.
        $count = (int) test_pdo()->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        T::assertTrue($count > 0, 'audit_logs must contain entries from the actions performed earlier in this test run');
    });

    T::run('Audit log viewer query filters by entity_type and free-text search without hiding other entries', function () {
        Audit::log('test.marker_action', 'test_marker_entity', 999, null, null, 'unique-marker-xyz');

        $byType = AuditLog::search(['entity_type' => 'test_marker_entity']);
        T::assertTrue(count($byType) >= 1, 'filtering by entity_type must return the marker row');
        foreach ($byType as $row) {
            T::assertSame('test_marker_entity', $row['entity_type'], 'entity_type filter must not leak rows of a different type');
        }

        $byText = AuditLog::search(['q' => 'unique-marker-xyz']);
        T::assertSame(1, count($byText), 'free-text search on notes must match exactly the marker row');
        T::assertSame('test.marker_action', $byText[0]['action'], 'the matched row must be the one just logged');

        $unfiltered = AuditLog::search([]);
        T::assertTrue(count($unfiltered) >= count($byType), 'no filter must return at least as many rows as any filtered query');
    });
};
