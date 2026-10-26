<?php

declare(strict_types=1);

use App\Models\InventoryItem;
use App\Services\InventoryService;

return function (): void {
    echo "InventoryTest\n";

    $pdo = test_pdo();
    $now = date('Y-m-d H:i:s');
    $pdo->prepare(
        "INSERT INTO inventory_items (sku, name, unit, reorder_threshold, current_quantity, average_cost, allow_negative_stock, is_active, created_at, updated_at)
         VALUES ('TEST-FEED', 'Test Goat Feed', 'kg', 20, 0, 0, 0, 1, :c, :u)"
    )->execute(['c' => $now, 'u' => $now]);
    $itemId = (int) $pdo->lastInsertId();

    T::run('Workflow #11: purchase inventory and verify stock changes', function () use ($itemId) {
        InventoryService::receiveStock($itemId, 100.0, 20.0, date('Y-m-d'), 'LOT-1', 'Test Supplier', null, 1);
        $item = InventoryItem::find($itemId);
        T::assertEqualsFloat(100.0, (float) $item['current_quantity'], 'current_quantity must equal the quantity received');
        T::assertEqualsFloat(20.0, (float) $item['average_cost'], 'average_cost must equal the unit cost of the only batch');
    });

    T::run('Receiving a second, differently-priced batch recalculates a correct weighted average cost', function () use ($itemId) {
        InventoryService::receiveStock($itemId, 100.0, 30.0, date('Y-m-d'), 'LOT-2', 'Test Supplier', null, 1);
        $item = InventoryItem::find($itemId);
        // 100kg@20 + 100kg@30 = 200kg, weighted avg = (2000+3000)/200 = 25
        T::assertEqualsFloat(25.0, (float) $item['average_cost'], 'weighted average cost across both batches');
        T::assertEqualsFloat(200.0, (float) $item['current_quantity'], 'total quantity across both batches');
    });

    T::run('Workflow #12: consume feed FIFO and verify stock decreases correctly', function () use ($itemId) {
        InventoryService::consumeStock($itemId, 120.0, 'vaccination', null, 1, 'Test consumption');
        $item = InventoryItem::find($itemId);
        T::assertEqualsFloat(80.0, (float) $item['current_quantity'], '200 - 120 consumed = 80 remaining');

        $batches = InventoryItem::batches($itemId);
        $lot1 = array_values(array_filter($batches, static fn ($b) => $b['batch_number'] === 'LOT-1'))[0] ?? null;
        T::assertNotNull($lot1, 'LOT-1 batch must still exist');
        T::assertEqualsFloat(0.0, (float) $lot1['quantity_remaining'], 'FIFO must fully deplete the older LOT-1 (100kg) first');

        $lot2 = array_values(array_filter($batches, static fn ($b) => $b['batch_number'] === 'LOT-2'))[0] ?? null;
        T::assertNotNull($lot2, 'LOT-2 batch must still exist');
        T::assertEqualsFloat(80.0, (float) $lot2['quantity_remaining'], 'remaining 20kg of the 120kg consumed must come from LOT-2, leaving 80kg');
    });

    T::run('Consuming more than available stock is refused when negative stock is not allowed', function () use ($itemId) {
        T::assertThrows(
            fn () => InventoryService::consumeStock($itemId, 999999.0, 'manual_adjustment', null, 1),
            'consuming more than available stock must fail when allow_negative_stock = 0'
        );
    });

    T::run('Manual stock adjustment creates a movement and updates the quantity', function () use ($itemId) {
        InventoryService::adjustStock($itemId, 50.0, 'Physical count correction', 1);
        $item = InventoryItem::find($itemId);
        T::assertEqualsFloat(50.0, (float) $item['current_quantity'], 'adjustment must set the exact corrected quantity');
    });
};
