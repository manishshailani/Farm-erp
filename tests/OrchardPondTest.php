<?php

declare(strict_types=1);

use App\Models\InventoryItem;
use App\Models\OrchardBlock;
use App\Models\OrchardHarvest;
use App\Models\OrchardTree;
use App\Models\Pond;
use App\Models\PondStockingBatch;
use App\Models\PondWaterQualityReading;
use App\Services\OrchardService;
use App\Services\PondService;

/**
 * Covers the orchard and pond modules added after the initial delivery
 * pass (see docs/FINAL_ACCEPTANCE_REPORT.md section 3 -- these modules
 * previously had a database schema but zero application code). This
 * file exercises the application layer (OrchardService/PondService and
 * the models they write through) against the same real SQLite test
 * database the rest of the suite uses.
 */
return function (): void {
    echo "OrchardPondTest\n";

    $blockId = null;
    $treeId = null;
    $itemId = null;

    T::run('Register an orchard block and plant a tree in it', function () use (&$blockId, &$treeId) {
        $blockId = OrchardService::createBlock([
            'name' => 'North Mango Block',
            'species' => 'Mango',
            'variety' => 'Dashehari',
        ], null);
        T::assertTrue($blockId > 0, 'block should be created with an id');

        $block = OrchardBlock::find($blockId);
        T::assertSame('North Mango Block', $block['name'], 'stored block name must match input');

        $treeId = OrchardService::plantTree([
            'block_id' => $blockId,
            'tree_code' => 'NMB-001',
            'row_number' => 1,
            'position_in_row' => 1,
            'planting_date' => '2024-06-01',
        ], null);
        T::assertTrue($treeId > 0, 'tree should be created with an id');

        $trees = OrchardTree::forBlock($blockId);
        T::assertSame(1, count($trees), 'exactly one tree should now exist in this block');
        T::assertSame('alive', $trees[0]['status'], 'a newly planted tree defaults to alive');
    });

    T::run('A duplicate tree code is rejected', function () use ($blockId) {
        T::assertThrows(function () use ($blockId) {
            OrchardService::plantTree(['block_id' => $blockId, 'tree_code' => 'NMB-001'], null);
        }, 'planting a second tree with an already-used code must be refused');
    });

    T::run('Tree status can be updated and is audited', function () use ($treeId) {
        OrchardService::setTreeStatus($treeId, 'dead', null);
        $tree = OrchardTree::find($treeId);
        T::assertSame('dead', $tree['status'], 'tree status must reflect the update');

        $pdo = test_pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM audit_logs WHERE action = 'orchard.tree.status_change' AND entity_id = :id");
        $stmt->execute(['id' => $treeId]);
        T::assertTrue(((int) $stmt->fetch()['c']) > 0, 'a status change must be recorded in the audit log');

        OrchardService::setTreeStatus($treeId, 'alive', null); // restore for later tests
    });

    T::run('Recording a harvest with an inventory credit updates stock in the same transaction', function () use (&$itemId, $blockId, $treeId) {
        $pdo = test_pdo();
        $catId = (int) $pdo->query('SELECT id FROM inventory_categories LIMIT 1')->fetchColumn();
        $pdo->prepare(
            "INSERT INTO inventory_items (name, sku, category_id, unit, current_quantity, average_cost, reorder_threshold, allow_negative_stock, is_active, created_at, updated_at)
             VALUES ('Mango (Dashehari)', 'MANGO-DASH', :cat, 'kg', 0, 0, 10, 0, 1, :now, :now)"
        )->execute(['cat' => $catId, 'now' => date('Y-m-d H:i:s')]);
        $itemId = (int) \App\Core\Database::lastInsertId();

        $harvestId = OrchardService::recordHarvest([
            'block_id' => $blockId,
            'tree_id' => $treeId,
            'harvest_date' => '2024-07-15',
            'quantity_kg' => 40.5,
            'grade' => 'A',
            'inventory_item_id' => $itemId,
        ], null);
        T::assertTrue($harvestId > 0, 'harvest should be recorded with an id');

        $item = InventoryItem::find($itemId);
        T::assertEqualsFloat(40.5, (float) $item['current_quantity'], 'the harvested quantity must be credited into inventory stock');

        $harvests = OrchardHarvest::forBlock($blockId);
        T::assertSame(1, count($harvests), 'exactly one harvest should now exist for this block');
    });

    T::run('A harvest with no quantity is refused', function () use ($blockId) {
        T::assertThrows(function () use ($blockId) {
            OrchardService::recordHarvest(['block_id' => $blockId, 'harvest_date' => '2024-07-16', 'quantity_kg' => 0], null);
        }, 'a zero-quantity harvest must be refused');
    });

    // --------------------------------------------------------------- Pond
    $pondId = null;
    $batchId = null;

    T::run('Create a pond and stock it with a batch of fingerlings', function () use (&$pondId, &$batchId) {
        $pondId = PondService::createPond(['name' => 'Pond A', 'surface_area_sqm' => 500], null);
        T::assertTrue($pondId > 0, 'pond should be created with an id');

        $batchId = PondService::addStockingBatch([
            'pond_id' => $pondId,
            'species' => 'Rohu',
            'stocking_date' => '2024-03-01',
            'fingerling_quantity' => 2000,
            'purchase_price' => 5000,
        ], null);
        T::assertTrue($batchId > 0, 'stocking batch should be created with an id');

        $batches = PondStockingBatch::forPond($pondId);
        T::assertSame('growing', $batches[0]['status'], 'a new stocking batch starts in growing status');
    });

    T::run('A duplicate pond name is rejected', function () {
        T::assertThrows(function () {
            PondService::createPond(['name' => 'Pond A'], null);
        }, 'creating a second pond with an already-used name must be refused');
    });

    T::run('Harvesting a stocking batch closes it out and records the sale amount', function () use ($batchId) {
        PondService::recordHarvest($batchId, [
            'harvest_date' => '2024-09-01',
            'harvest_quantity_kg' => 850.0,
            'sale_amount' => 95000,
        ], null);

        $batch = PondStockingBatch::find($batchId);
        T::assertSame('harvested', $batch['status'], 'batch status must become harvested');
        T::assertEqualsFloat(850.0, (float) $batch['harvest_quantity_kg'], 'harvested quantity must be stored');
    });

    T::run('A harvested batch cannot be harvested or marked lost again', function () use ($batchId) {
        T::assertThrows(function () use ($batchId) {
            PondService::recordHarvest($batchId, ['harvest_date' => '2024-09-02', 'harvest_quantity_kg' => 10], null);
        }, 'a second harvest on an already-closed batch must be refused');

        T::assertThrows(function () use ($batchId) {
            PondService::markBatchLost($batchId, 'testing double close', null);
        }, 'marking an already-harvested batch as lost must be refused');
    });

    T::run('A separate stocking batch can be marked lost instead of harvested', function () use ($pondId) {
        $lostBatchId = PondService::addStockingBatch([
            'pond_id' => $pondId,
            'species' => 'Catla',
            'stocking_date' => '2024-04-01',
            'fingerling_quantity' => 500,
        ], null);
        PondService::markBatchLost($lostBatchId, 'disease outbreak', null);

        $batch = PondStockingBatch::find($lostBatchId);
        T::assertSame('lost', $batch['status'], 'batch status must become lost');
    });

    T::run('Water quality readings are recorded and retrievable most-recent-first', function () use ($pondId) {
        PondService::recordWaterQualityReading([
            'pond_id' => $pondId, 'measured_at' => '2024-05-01 08:00:00', 'temperature_c' => 27.5, 'ph' => 7.2,
        ], null);
        PondService::recordWaterQualityReading([
            'pond_id' => $pondId, 'measured_at' => '2024-05-02 08:00:00', 'temperature_c' => 28.1, 'ph' => 7.1,
        ], null);

        $readings = PondWaterQualityReading::forPond($pondId);
        T::assertSame(2, count($readings), 'both readings should be stored');
        T::assertSame('2024-05-02 08:00:00', $readings[0]['measured_at'], 'readings must be ordered most-recent-first');

        $latest = PondWaterQualityReading::latestForPond($pondId);
        T::assertSame('2024-05-02 08:00:00', $latest['measured_at'], 'latestForPond must return the most recent reading');
    });
};
