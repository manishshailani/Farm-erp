<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use RuntimeException;

/**
 * Transactional stock management.
 *
 * Business rules (see spec section 8 and the project's reliability
 * requirements):
 *  - Every quantity change to inventory_items.current_quantity happens
 *    inside the same DB transaction as a stock_movements row -- the two
 *    must never be able to drift apart. There is deliberately no public
 *    method that updates current_quantity directly.
 *  - Stock is consumed FIFO: oldest inventory_batches.received_date with
 *    quantity_remaining > 0 first. This both matches common small-farm
 *    practice (use older feed/medicine before it expires) and gives a
 *    defensible per-batch cost basis for the financial reports.
 *  - Negative stock is refused unless the item's allow_negative_stock
 *    flag is explicitly set, in which case the shortfall is still logged
 *    as an audit event (never silent).
 */
final class InventoryService
{
    public static function receiveStock(
        int $itemId,
        float $quantity,
        float $unitCost,
        string $receivedDate,
        ?string $batchNumber,
        ?string $supplierName,
        ?string $expiryDate,
        ?int $userId,
    ): int {
        if ($quantity <= 0) {
            throw new RuntimeException('Received quantity must be greater than zero.');
        }

        return (int) Database::transaction(function ($pdo) use (
            $itemId, $quantity, $unitCost, $receivedDate, $batchNumber, $supplierName, $expiryDate, $userId
        ) {
            $now = date('Y-m-d H:i:s');

            $pdo->prepare(
                'INSERT INTO inventory_batches (item_id, batch_number, quantity_received, quantity_remaining, unit_cost, supplier_name, received_date, expiry_date, created_at)
                 VALUES (:item_id, :batch_number, :qty, :qty, :unit_cost, :supplier, :received_date, :expiry_date, :created_at)'
            )->execute([
                'item_id' => $itemId,
                'batch_number' => $batchNumber,
                'qty' => $quantity,
                'unit_cost' => $unitCost,
                'supplier' => $supplierName,
                'received_date' => $receivedDate,
                'expiry_date' => $expiryDate,
                'created_at' => $now,
            ]);
            $batchId = (int) Database::lastInsertId();

            $pdo->prepare(
                'INSERT INTO stock_movements (item_id, batch_id, movement_type, quantity, unit_cost, reference_type, reference_id, created_by, created_at)
                 VALUES (:item_id, :batch_id, "in", :qty, :unit_cost, "purchase", :batch_id2, :user_id, :created_at)'
            )->execute([
                'item_id' => $itemId,
                'batch_id' => $batchId,
                'qty' => $quantity,
                'unit_cost' => $unitCost,
                'batch_id2' => $batchId,
                'user_id' => $userId,
                'created_at' => $now,
            ]);

            self::recalculateItemAggregates($pdo, $itemId);

            Audit::log('inventory.receive', 'inventory_item', $itemId, null, [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'batch_id' => $batchId,
            ]);

            return $batchId;
        });
    }

    /**
     * Consume $quantity of $itemId, oldest batch first. $referenceType /
     * $referenceId identify why (vaccination, feeding, orchard_activity,
     * order, manual_adjustment) so stock_movements stays traceable to the
     * business event that caused it.
     */
    public static function consumeStock(
        int $itemId,
        float $quantity,
        string $referenceType,
        ?int $referenceId,
        ?int $userId,
        ?string $notes = null,
    ): void {
        if ($quantity <= 0) {
            throw new RuntimeException('Quantity to consume must be greater than zero.');
        }

        Database::transaction(function ($pdo) use ($itemId, $quantity, $referenceType, $referenceId, $userId, $notes) {
            $item = self::lockItem($pdo, $itemId);
            $now = date('Y-m-d H:i:s');
            $remainingToConsume = $quantity;

            $available = (float) $item['current_quantity'];
            if ($available < $quantity && !((bool) $item['allow_negative_stock'])) {
                throw new RuntimeException(sprintf(
                    'Insufficient stock for "%s": requested %s, available %s.',
                    $item['name'],
                    number_format($quantity, 2),
                    number_format($available, 2)
                ));
            }

            $batches = $pdo->prepare(
                'SELECT * FROM inventory_batches WHERE item_id = :item_id AND quantity_remaining > 0 ORDER BY received_date ASC, id ASC'
            );
            $batches->execute(['item_id' => $itemId]);

            foreach ($batches->fetchAll() as $batch) {
                if ($remainingToConsume <= 0) {
                    break;
                }
                $take = min($remainingToConsume, (float) $batch['quantity_remaining']);
                if ($take <= 0) {
                    continue;
                }

                $pdo->prepare('UPDATE inventory_batches SET quantity_remaining = quantity_remaining - :t WHERE id = :id')
                    ->execute(['t' => $take, 'id' => $batch['id']]);

                $pdo->prepare(
                    'INSERT INTO stock_movements (item_id, batch_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at)
                     VALUES (:item_id, :batch_id, "out", :qty, :unit_cost, :ref_type, :ref_id, :notes, :user_id, :created_at)'
                )->execute([
                    'item_id' => $itemId,
                    'batch_id' => $batch['id'],
                    'qty' => $take,
                    'unit_cost' => $batch['unit_cost'],
                    'ref_type' => $referenceType,
                    'ref_id' => $referenceId,
                    'notes' => $notes,
                    'user_id' => $userId,
                    'created_at' => $now,
                ]);

                $remainingToConsume -= $take;
            }

            if ($remainingToConsume > 0) {
                // Allowed only because allow_negative_stock was checked
                // above; record the shortfall as its own movement with no
                // batch, and flag it clearly in the audit trail.
                $pdo->prepare(
                    'INSERT INTO stock_movements (item_id, batch_id, movement_type, quantity, reference_type, reference_id, notes, created_by, created_at)
                     VALUES (:item_id, NULL, "out", :qty, :ref_type, :ref_id, :notes, :user_id, :created_at)'
                )->execute([
                    'item_id' => $itemId,
                    'qty' => $remainingToConsume,
                    'ref_type' => $referenceType,
                    'ref_id' => $referenceId,
                    'notes' => trim(($notes ?? '') . ' [WARNING: exceeded available batched stock; negative stock allowed for this item]'),
                    'user_id' => $userId,
                    'created_at' => $now,
                ]);
                Audit::log('inventory.negative_stock_warning', 'inventory_item', $itemId, null, ['shortfall' => $remainingToConsume]);
            }

            self::recalculateItemAggregates($pdo, $itemId);
        });
    }

    public static function adjustStock(int $itemId, float $newQuantity, string $reason, ?int $userId): void
    {
        Database::transaction(function ($pdo) use ($itemId, $newQuantity, $reason, $userId) {
            $item = self::lockItem($pdo, $itemId);
            $diff = $newQuantity - (float) $item['current_quantity'];
            $now = date('Y-m-d H:i:s');

            $pdo->prepare(
                'INSERT INTO stock_movements (item_id, movement_type, quantity, reference_type, notes, created_by, created_at)
                 VALUES (:item_id, "adjustment", :qty, "manual_adjustment", :notes, :user_id, :created_at)'
            )->execute([
                'item_id' => $itemId,
                'qty' => abs($diff),
                'notes' => $reason,
                'user_id' => $userId,
                'created_at' => $now,
            ]);

            $pdo->prepare('UPDATE inventory_items SET current_quantity = :q, updated_at = :u WHERE id = :id')
                ->execute(['q' => $newQuantity, 'u' => $now, 'id' => $itemId]);

            Audit::log('inventory.adjust', 'inventory_item', $itemId, ['quantity' => $item['current_quantity']], ['quantity' => $newQuantity, 'reason' => $reason]);
        });
    }

    /** @return array<string,mixed> */
    private static function lockItem(\PDO $pdo, int $itemId): array
    {
        // "SELECT ... FOR UPDATE" row-locks the item under MySQL/InnoDB so
        // two concurrent consumers cannot both read the same
        // current_quantity and double-spend it; SQLite has no row-level
        // locking (it serializes writers at the database level instead),
        // so the clause is simply omitted there -- the surrounding
        // transaction still gives atomicity on both engines.
        $forUpdate = Database::driver() === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare('SELECT * FROM inventory_items WHERE id = :id' . $forUpdate);
        $stmt->execute(['id' => $itemId]);
        $item = $stmt->fetch();
        if ($item === false) {
            throw new RuntimeException("Inventory item #{$itemId} not found.");
        }
        return $item;
    }

    private static function recalculateItemAggregates(\PDO $pdo, int $itemId): void
    {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity_remaining),0) AS qty FROM inventory_batches WHERE item_id = :i');
        $stmt->execute(['i' => $itemId]);
        $qty = (float) $stmt->fetch()['qty'];

        $costStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(quantity_remaining * unit_cost),0) AS total_value, COALESCE(SUM(quantity_remaining),0) AS total_qty
             FROM inventory_batches WHERE item_id = :i AND quantity_remaining > 0'
        );
        $costStmt->execute(['i' => $itemId]);
        $costRow = $costStmt->fetch();
        $avgCost = ((float) $costRow['total_qty']) > 0 ? ((float) $costRow['total_value']) / ((float) $costRow['total_qty']) : 0.0;

        $pdo->prepare('UPDATE inventory_items SET current_quantity = :q, average_cost = :c, updated_at = :u WHERE id = :id')
            ->execute(['q' => $qty, 'c' => $avgCost, 'u' => date('Y-m-d H:i:s'), 'id' => $itemId]);
    }
}
