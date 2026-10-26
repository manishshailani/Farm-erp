<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Models\Pond;
use RuntimeException;

/**
 * Pond / aquaculture management: pond master records, stocking batches
 * (a cohort of fingerlings stocked on a given date), their eventual
 * harvest or loss, and water-quality readings.
 *
 * Scope note (see docs/FINAL_ACCEPTANCE_REPORT.md): this is the
 * basic-CRUD-plus-harvest/economics slice the schema was designed for.
 * It does NOT implement automated water-quality alerting (e.g. paging
 * someone when dissolved oxygen drops below a threshold) or feed-ration
 * calculation -- readings are recorded and viewable, but nothing in this
 * delivery evaluates them against a threshold or notifies anyone.
 */
final class PondService
{
    public static function createPond(array $data, ?int $userId): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Pond name is required.');
        }
        if (Pond::nameExists($name)) {
            throw new RuntimeException("A pond named \"{$name}\" already exists.");
        }

        return (int) Database::transaction(function ($pdo) use ($data, $name) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO ponds (name, surface_area_sqm, estimated_depth_m, water_source, notes, created_at)
                 VALUES (:name, :area, :depth, :source, :notes, :created_at)'
            )->execute([
                'name' => $name,
                'area' => !empty($data['surface_area_sqm']) ? (float) $data['surface_area_sqm'] : null,
                'depth' => !empty($data['estimated_depth_m']) ? (float) $data['estimated_depth_m'] : null,
                'source' => ($data['water_source'] ?? '') ?: null,
                'notes' => ($data['notes'] ?? '') ?: null,
                'created_at' => $now,
            ]);
            $pondId = (int) Database::lastInsertId();
            Audit::log('pond.create', 'pond', $pondId, null, ['name' => $name]);
            return $pondId;
        });
    }

    public static function addStockingBatch(array $data, ?int $userId): int
    {
        $pondId = (int) ($data['pond_id'] ?? 0);
        $species = trim((string) ($data['species'] ?? ''));
        $quantity = (int) ($data['fingerling_quantity'] ?? 0);
        $stockingDate = (string) ($data['stocking_date'] ?? '');
        if ($pondId <= 0 || $species === '' || $quantity <= 0 || $stockingDate === '') {
            throw new RuntimeException('Pond, species, a positive fingerling quantity, and a stocking date are required.');
        }

        return (int) Database::transaction(function ($pdo) use ($data, $pondId, $species, $quantity, $stockingDate) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO pond_stocking_batches
                    (pond_id, species, stocking_date, fingerling_quantity, average_stocking_weight_g, purchase_price, status, created_at)
                 VALUES (:pond_id, :species, :stocking_date, :qty, :avg_weight, :price, "growing", :created_at)'
            )->execute([
                'pond_id' => $pondId,
                'species' => $species,
                'stocking_date' => $stockingDate,
                'qty' => $quantity,
                'avg_weight' => !empty($data['average_stocking_weight_g']) ? (float) $data['average_stocking_weight_g'] : null,
                'price' => !empty($data['purchase_price']) ? (float) $data['purchase_price'] : null,
                'created_at' => $now,
            ]);
            $batchId = (int) Database::lastInsertId();
            Audit::log('pond.stocking.create', 'pond_stocking_batch', $batchId, null, [
                'pond_id' => $pondId, 'species' => $species, 'fingerling_quantity' => $quantity,
            ]);
            return $batchId;
        });
    }

    /**
     * Marks a stocking batch as harvested, recording the harvested
     * weight, the harvest date, and (optionally) the sale amount
     * realized. The sale amount is stored purely as a record on the
     * batch -- it is NOT automatically posted as income to
     * FinancialTransaction/FinanceService, because doing so would
     * require guessing which account the money was deposited into; if
     * the farm wants this reflected in the ledger, record it explicitly
     * via Finance → Transactions, referencing this batch in the notes.
     */
    public static function recordHarvest(int $batchId, array $data, ?int $userId): void
    {
        $quantityKg = (float) ($data['harvest_quantity_kg'] ?? 0);
        $harvestDate = (string) ($data['harvest_date'] ?? '');
        if ($quantityKg <= 0 || $harvestDate === '') {
            throw new RuntimeException('A positive harvested quantity and a harvest date are required.');
        }

        Database::transaction(function ($pdo) use ($batchId, $data, $quantityKg, $harvestDate) {
            $before = $pdo->prepare('SELECT status FROM pond_stocking_batches WHERE id = :id');
            $before->execute(['id' => $batchId]);
            $row = $before->fetch();
            if ($row === false) {
                throw new RuntimeException("Stocking batch #{$batchId} not found.");
            }
            if ($row['status'] !== 'growing') {
                throw new RuntimeException('This batch has already been closed out (harvested or marked lost).');
            }

            $pdo->prepare(
                'UPDATE pond_stocking_batches
                 SET status = "harvested", harvest_quantity_kg = :qty, harvest_date = :date, sale_amount = :sale
                 WHERE id = :id'
            )->execute([
                'qty' => $quantityKg,
                'date' => $harvestDate,
                'sale' => !empty($data['sale_amount']) ? (float) $data['sale_amount'] : null,
                'id' => $batchId,
            ]);

            Audit::log('pond.stocking.harvest', 'pond_stocking_batch', $batchId, ['status' => 'growing'], [
                'status' => 'harvested', 'harvest_quantity_kg' => $quantityKg,
            ]);
        });
    }

    public static function markBatchLost(int $batchId, ?string $notes, ?int $userId): void
    {
        Database::transaction(function ($pdo) use ($batchId, $notes) {
            $before = $pdo->prepare('SELECT status FROM pond_stocking_batches WHERE id = :id');
            $before->execute(['id' => $batchId]);
            $row = $before->fetch();
            if ($row === false) {
                throw new RuntimeException("Stocking batch #{$batchId} not found.");
            }
            if ($row['status'] !== 'growing') {
                throw new RuntimeException('This batch has already been closed out (harvested or marked lost).');
            }
            $pdo->prepare('UPDATE pond_stocking_batches SET status = "lost" WHERE id = :id')->execute(['id' => $batchId]);
            Audit::log('pond.stocking.lost', 'pond_stocking_batch', $batchId, ['status' => 'growing'], ['status' => 'lost'], $notes);
        });
    }

    public static function recordWaterQualityReading(array $data, ?int $userId): int
    {
        $pondId = (int) ($data['pond_id'] ?? 0);
        $measuredAt = (string) ($data['measured_at'] ?? '');
        if ($pondId <= 0 || $measuredAt === '') {
            throw new RuntimeException('Pond and measurement date/time are required.');
        }

        return (int) Database::transaction(function ($pdo) use ($data, $pondId, $measuredAt, $userId) {
            $pdo->prepare(
                'INSERT INTO pond_water_quality_readings
                    (pond_id, measured_at, temperature_c, dissolved_oxygen_mgl, ph, ammonia_mgl, measurement_method, recorded_by)
                 VALUES (:pond_id, :measured_at, :temp, :oxygen, :ph, :ammonia, :method, :recorded_by)'
            )->execute([
                'pond_id' => $pondId,
                'measured_at' => $measuredAt,
                'temp' => isset($data['temperature_c']) && $data['temperature_c'] !== '' ? (float) $data['temperature_c'] : null,
                'oxygen' => isset($data['dissolved_oxygen_mgl']) && $data['dissolved_oxygen_mgl'] !== '' ? (float) $data['dissolved_oxygen_mgl'] : null,
                'ph' => isset($data['ph']) && $data['ph'] !== '' ? (float) $data['ph'] : null,
                'ammonia' => isset($data['ammonia_mgl']) && $data['ammonia_mgl'] !== '' ? (float) $data['ammonia_mgl'] : null,
                'method' => ($data['measurement_method'] ?? '') ?: 'manual',
                'recorded_by' => $userId,
            ]);
            $readingId = (int) Database::lastInsertId();
            Audit::log('pond.water_reading.record', 'pond_water_quality_reading', $readingId, null, [
                'pond_id' => $pondId, 'measured_at' => $measuredAt,
            ]);
            return $readingId;
        });
    }
}
