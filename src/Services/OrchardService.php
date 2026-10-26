<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Models\OrchardTree;
use RuntimeException;

/**
 * Orchard management: blocks (a planted area of one species/variety),
 * trees within a block, and harvest events.
 *
 * Scope note (see docs/FINAL_ACCEPTANCE_REPORT.md): this implements the
 * CRUD + harvest-logging layer the database schema was designed for. It
 * does NOT implement maintenance scheduling (pruning/spraying/fertigation
 * calendars), yield forecasting, or any pest/disease tracking -- none of
 * that has a table in the schema (see the 007_orchard_pond.sql migration
 * under database/migrations) and none of it was asked for in a way that was testable against real
 * data in this delivery. What exists here is deliberately the smaller,
 * verifiable slice: register what you planted, record what you picked.
 */
final class OrchardService
{
    public static function createBlock(array $data, ?int $userId): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $species = trim((string) ($data['species'] ?? ''));
        if ($name === '' || $species === '') {
            throw new RuntimeException('Block name and species are required.');
        }

        return (int) Database::transaction(function ($pdo) use ($data, $name, $species) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO orchard_blocks (name, species, variety, notes, created_at)
                 VALUES (:name, :species, :variety, :notes, :created_at)'
            )->execute([
                'name' => $name,
                'species' => $species,
                'variety' => ($data['variety'] ?? '') ?: null,
                'notes' => ($data['notes'] ?? '') ?: null,
                'created_at' => $now,
            ]);
            $blockId = (int) Database::lastInsertId();
            Audit::log('orchard.block.create', 'orchard_block', $blockId, null, ['name' => $name, 'species' => $species]);
            return $blockId;
        });
    }

    public static function plantTree(array $data, ?int $userId): int
    {
        $blockId = (int) ($data['block_id'] ?? 0);
        $treeCode = trim((string) ($data['tree_code'] ?? ''));
        if ($blockId <= 0 || $treeCode === '') {
            throw new RuntimeException('A block and a unique tree code are required.');
        }
        if (OrchardTree::codeExists($treeCode)) {
            throw new RuntimeException("Tree code \"{$treeCode}\" is already in use.");
        }

        return (int) Database::transaction(function ($pdo) use ($data, $blockId, $treeCode) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO orchard_trees (tree_code, block_id, tree_row, position_in_row, planting_date, status, notes, created_at)
                 VALUES (:code, :block_id, :row, :pos, :planted, :status, :notes, :created_at)'
            )->execute([
                'code' => $treeCode,
                'block_id' => $blockId,
                'row' => !empty($data['row_number']) ? (int) $data['row_number'] : null,
                'pos' => !empty($data['position_in_row']) ? (int) $data['position_in_row'] : null,
                'planted' => ($data['planting_date'] ?? '') ?: null,
                'status' => ($data['status'] ?? '') ?: 'alive',
                'notes' => ($data['notes'] ?? '') ?: null,
                'created_at' => $now,
            ]);
            $treeId = (int) Database::lastInsertId();
            Audit::log('orchard.tree.create', 'orchard_tree', $treeId, null, ['tree_code' => $treeCode, 'block_id' => $blockId]);
            return $treeId;
        });
    }

    /**
     * Updates a tree's lifecycle status (alive / dead / replaced). This
     * is a status-only change -- it does not delete or replant the row,
     * so a tree's history (its harvests) always remains attributable to
     * the same tree_id even after it dies or is replaced.
     */
    public static function setTreeStatus(int $treeId, string $status, ?int $userId): void
    {
        $allowed = ['alive', 'dead', 'replaced'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid tree status.');
        }
        Database::transaction(function ($pdo) use ($treeId, $status) {
            $before = $pdo->prepare('SELECT status FROM orchard_trees WHERE id = :id');
            $before->execute(['id' => $treeId]);
            $row = $before->fetch();
            if ($row === false) {
                throw new RuntimeException("Tree #{$treeId} not found.");
            }
            $pdo->prepare('UPDATE orchard_trees SET status = :s WHERE id = :id')
                ->execute(['s' => $status, 'id' => $treeId]);
            Audit::log('orchard.tree.status_change', 'orchard_tree', $treeId, ['status' => $row['status']], ['status' => $status]);
        });
    }

    /**
     * Records a harvest event against a block (and, optionally, a
     * specific tree within it). If $data['inventory_item_id'] is set,
     * the harvested quantity is credited into that inventory item via
     * InventoryService::receiveStock() in the SAME database transaction,
     * so a harvest and its inventory receipt can never exist
     * independently of each other.
     *
     * The optional unit cost for that inventory receipt defaults to 0.00
     * (own-grown produce has no purchase cost) -- callers may pass
     * $data['credit_unit_cost'] to value it differently (e.g. an
     * estimated market rate), but this delivery does not infer one.
     */
    public static function recordHarvest(array $data, ?int $userId): int
    {
        $blockId = (int) ($data['block_id'] ?? 0);
        $quantityKg = (float) ($data['quantity_kg'] ?? 0);
        $harvestDate = (string) ($data['harvest_date'] ?? '');
        if ($blockId <= 0 || $quantityKg <= 0 || $harvestDate === '') {
            throw new RuntimeException('Block, a positive quantity, and a harvest date are required.');
        }

        return (int) Database::transaction(function ($pdo) use ($data, $blockId, $quantityKg, $harvestDate, $userId) {
            $now = date('Y-m-d H:i:s');
            $treeId = !empty($data['tree_id']) ? (int) $data['tree_id'] : null;
            $inventoryItemId = !empty($data['inventory_item_id']) ? (int) $data['inventory_item_id'] : null;

            $pdo->prepare(
                'INSERT INTO orchard_harvests (block_id, tree_id, harvest_date, quantity_kg, grade, inventory_item_id, recorded_by, created_at)
                 VALUES (:block_id, :tree_id, :harvest_date, :qty, :grade, :inv_item, :recorded_by, :created_at)'
            )->execute([
                'block_id' => $blockId,
                'tree_id' => $treeId,
                'harvest_date' => $harvestDate,
                'qty' => $quantityKg,
                'grade' => ($data['grade'] ?? '') ?: null,
                'inv_item' => $inventoryItemId,
                'recorded_by' => $userId,
                'created_at' => $now,
            ]);
            $harvestId = (int) Database::lastInsertId();

            if ($inventoryItemId !== null) {
                $unitCost = isset($data['credit_unit_cost']) && $data['credit_unit_cost'] !== ''
                    ? (float) $data['credit_unit_cost']
                    : 0.0;
                InventoryService::receiveStock(
                    $inventoryItemId,
                    $quantityKg,
                    $unitCost,
                    $harvestDate,
                    'orchard-harvest-' . $harvestId,
                    'Own orchard harvest',
                    null,
                    $userId
                );
            }

            Audit::log('orchard.harvest.record', 'orchard_harvest', $harvestId, null, [
                'block_id' => $blockId,
                'tree_id' => $treeId,
                'quantity_kg' => $quantityKg,
                'inventory_item_id' => $inventoryItemId,
            ]);

            return $harvestId;
        });
    }
}
