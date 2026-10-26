<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use RuntimeException;

/**
 * Livestock registration, RFID tag lifecycle, scanning workflow, and
 * weight/vaccination/lifecycle event recording.
 *
 * RFID hardware note (spec section 5.2): this service is deliberately
 * hardware-agnostic. A USB keyboard-wedge RFID reader types the tag
 * number into whatever text field has focus and then sends Enter -- from
 * the web application's point of view that is indistinguishable from an
 * operator typing the number by hand and pressing Enter, so the same
 * "scan" form field and the same ScanTag() call serve both a real
 * keyboard-wedge reader and manual entry with zero extra code. A serial
 * (COM-port) reader is different: a browser cannot open a serial port by
 * itself, so that path requires a small local bridge program (documented
 * in docs/RFID_SETUP_GUIDE.md) that reads the serial port and either (a)
 * simulates keystrokes, or (b) posts the scanned tag to this
 * application's /livestock/rfid/scan endpoint over HTTP on localhost.
 * Neither this class nor any other part of the delivered code talks to a
 * serial port directly, and no serial-reader integration has been tested
 * in this environment -- see the final acceptance report.
 */
final class LivestockService
{
    public static function registerAnimal(array $data, ?int $userId): int
    {
        return (int) Database::transaction(function ($pdo) use ($data, $userId) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO animals (animal_code, species_id, breed_id, name, sex, date_of_birth, estimated_age_months,
                    source, purchase_date, purchase_cost, dam_id, sire_id, pen_location, status, current_weight_kg,
                    health_status, notes, created_by, created_at, updated_at)
                 VALUES (:animal_code, :species_id, :breed_id, :name, :sex, :dob, :age_months,
                    :source, :purchase_date, :purchase_cost, :dam_id, :sire_id, :pen_location, :status, :weight,
                    :health_status, :notes, :created_by, :created_at, :updated_at)'
            )->execute([
                'animal_code' => $data['animal_code'],
                'species_id' => (int) $data['species_id'],
                'breed_id' => !empty($data['breed_id']) ? (int) $data['breed_id'] : null,
                'name' => $data['name'] ?? '' ?: null,
                'sex' => $data['sex'],
                'dob' => $data['date_of_birth'] ?? '' ?: null,
                'age_months' => !empty($data['estimated_age_months']) ? (int) $data['estimated_age_months'] : null,
                'source' => $data['source'],
                'purchase_date' => $data['purchase_date'] ?? '' ?: null,
                'purchase_cost' => !empty($data['purchase_cost']) ? (float) $data['purchase_cost'] : null,
                // !empty() (not a '!== '' check) is deliberate here: an
                // absent key, an empty string AND "0" must all mean "no
                // parent recorded" -- treating "0" as a real id would
                // insert a non-existent animals.id and violate the FK
                // (this was exactly the bug caught by the end-to-end
                // smoke test; see docs/FINAL_ACCEPTANCE_REPORT.md).
                'dam_id' => !empty($data['dam_id']) ? (int) $data['dam_id'] : null,
                'sire_id' => !empty($data['sire_id']) ? (int) $data['sire_id'] : null,
                'pen_location' => $data['pen_location'] ?? '' ?: null,
                'status' => 'active',
                'weight' => !empty($data['current_weight_kg']) ? (float) $data['current_weight_kg'] : null,
                'health_status' => $data['health_status'] ?? '' ?: 'healthy',
                'notes' => $data['notes'] ?? '' ?: null,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $animalId = (int) Database::lastInsertId();

            if (!empty($data['current_weight_kg'])) {
                $pdo->prepare(
                    'INSERT INTO animal_weight_events (animal_id, recorded_at, weight_kg, recorded_by, notes)
                     VALUES (:a, :t, :w, :u, "Initial weight at registration")'
                )->execute(['a' => $animalId, 't' => $now, 'w' => (float) $data['current_weight_kg'], 'u' => $userId]);
            }

            Audit::log('livestock.register', 'animal', $animalId, null, $data);
            return $animalId;
        });
    }

    public static function assignTag(int $animalId, string $tagNumber, ?int $userId): void
    {
        Database::transaction(function ($pdo) use ($animalId, $tagNumber, $userId) {
            $existing = $pdo->prepare('SELECT id, animal_id FROM rfid_tags WHERE tag_number = :t AND is_active = 1');
            $existing->execute(['t' => $tagNumber]);
            $row = $existing->fetch();
            if ($row !== false) {
                throw new RuntimeException("Tag {$tagNumber} is already active on animal #{$row['animal_id']}. Deactivate it there first.");
            }

            $pdo->prepare(
                'INSERT INTO rfid_tags (tag_number, animal_id, is_active, assigned_at, assigned_by) VALUES (:t, :a, 1, :n, :u)'
            )->execute(['t' => $tagNumber, 'a' => $animalId, 'n' => date('Y-m-d H:i:s'), 'u' => $userId]);

            Audit::log('livestock.rfid.assign', 'animal', $animalId, null, ['tag_number' => $tagNumber]);
        });
    }

    public static function deactivateTag(int $tagId, string $reason, ?int $userId): void
    {
        Database::transaction(function ($pdo) use ($tagId, $reason) {
            $pdo->prepare(
                'UPDATE rfid_tags SET is_active = 0, deactivated_at = :n, deactivation_reason = :r WHERE id = :id'
            )->execute(['n' => date('Y-m-d H:i:s'), 'r' => $reason, 'id' => $tagId]);
            Audit::log('livestock.rfid.deactivate', 'rfid_tag', $tagId, null, ['reason' => $reason]);
        });
    }

    /**
     * Resolve a scanned/typed tag number to an animal, logging the scan
     * either way (an unmatched scan is still evidence worth keeping --
     * e.g. for noticing a reader malfunctioning or a lost tag).
     *
     * @return array<string,mixed>|null the animal row, or null if unmatched/inactive
     */
    public static function resolveTag(string $tagNumber, ?int $userId): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT a.* FROM rfid_tags t JOIN animals a ON a.id = t.animal_id WHERE t.tag_number = :t AND t.is_active = 1'
        );
        $stmt->execute(['t' => $tagNumber]);
        $animal = $stmt->fetch();

        $pdo->prepare(
            'INSERT INTO rfid_scan_log (tag_number, animal_id, matched, scanned_by, scanned_at) VALUES (:t, :a, :m, :u, :n)'
        )->execute([
            't' => $tagNumber,
            'a' => $animal !== false ? $animal['id'] : null,
            'm' => $animal !== false ? 1 : 0,
            'u' => $userId,
            'n' => date('Y-m-d H:i:s'),
        ]);

        return $animal === false ? null : $animal;
    }

    public static function recordWeight(int $animalId, float $weightKg, string $recordedAt, ?string $notes, ?int $userId): void
    {
        Database::transaction(function ($pdo) use ($animalId, $weightKg, $recordedAt, $notes, $userId) {
            $pdo->prepare(
                'INSERT INTO animal_weight_events (animal_id, recorded_at, weight_kg, recorded_by, notes) VALUES (:a, :t, :w, :u, :n)'
            )->execute(['a' => $animalId, 't' => $recordedAt, 'w' => $weightKg, 'u' => $userId, 'n' => $notes]);

            $pdo->prepare('UPDATE animals SET current_weight_kg = :w, updated_at = :u WHERE id = :id')
                ->execute(['w' => $weightKg, 'u' => date('Y-m-d H:i:s'), 'id' => $animalId]);

            Audit::log('livestock.weight', 'animal', $animalId, null, ['weight_kg' => $weightKg]);
        });
    }

    public static function recordLifecycleEvent(int $animalId, string $eventType, string $eventDate, array $details, ?int $userId): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO animal_lifecycle_events (animal_id, event_type, event_date, details, recorded_by, created_at)
             VALUES (:a, :type, :date, :details, :u, :created_at)'
        )->execute([
            'a' => $animalId,
            'type' => $eventType,
            'date' => $eventDate,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'u' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('livestock.lifecycle_event', 'animal', $animalId, null, ['event_type' => $eventType] + $details);
    }

    /**
     * Record a vaccination/treatment. If $inventoryItemId is given, the
     * dose is deducted from that inventory item's stock in the SAME
     * transaction, so a vaccination can never be recorded without the
     * corresponding stock consumption (or vice versa).
     */
    public static function recordVaccination(array $data, ?int $userId): int
    {
        return (int) Database::transaction(function ($pdo) use ($data, $userId) {
            $pdo->prepare(
                'INSERT INTO vaccinations (animal_id, vaccine_name, target_disease, dose, administered_date, batch_number,
                    expiry_date, administered_by, veterinarian, next_due_date, follow_up_notes, inventory_item_id, created_by, created_at)
                 VALUES (:animal_id, :vaccine_name, :target_disease, :dose, :administered_date, :batch_number,
                    :expiry_date, :administered_by, :veterinarian, :next_due_date, :follow_up_notes, :inventory_item_id, :created_by, :created_at)'
            )->execute([
                'animal_id' => (int) $data['animal_id'],
                'vaccine_name' => $data['vaccine_name'],
                'target_disease' => ($data['target_disease'] ?? '') ?: null,
                'dose' => ($data['dose'] ?? '') ?: null,
                'administered_date' => $data['administered_date'],
                'batch_number' => ($data['batch_number'] ?? '') ?: null,
                'expiry_date' => ($data['expiry_date'] ?? '') ?: null,
                'administered_by' => ($data['administered_by'] ?? '') ?: null,
                'veterinarian' => ($data['veterinarian'] ?? '') ?: null,
                'next_due_date' => ($data['next_due_date'] ?? '') ?: null,
                'follow_up_notes' => ($data['follow_up_notes'] ?? '') ?: null,
                'inventory_item_id' => !empty($data['inventory_item_id']) ? (int) $data['inventory_item_id'] : null,
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $vaccinationId = (int) Database::lastInsertId();

            if (!empty($data['inventory_item_id']) && !empty($data['doses_consumed'])) {
                InventoryService::consumeStock(
                    (int) $data['inventory_item_id'],
                    (float) $data['doses_consumed'],
                    'vaccination',
                    $vaccinationId,
                    $userId,
                    'Vaccination dose for animal #' . $data['animal_id']
                );
            }

            Audit::log('livestock.vaccination', 'animal', (int) $data['animal_id'], null, $data);
            return $vaccinationId;
        });
    }

    public static function dueVaccinations(string $asOfDate, int $withinDays = 14): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT v.*, a.animal_code, a.name AS animal_name
             FROM vaccinations v JOIN animals a ON a.id = v.animal_id
             WHERE v.next_due_date IS NOT NULL
               AND v.next_due_date <= date(:asof, :window)
             ORDER BY v.next_due_date ASC'
        );
        // SQLite date() supports "+N days" modifiers; MySQL does not run
        // this exact function, so the controller/report layer calling
        // this must be driver-aware for production use -- documented in
        // docs/DATABASE_PORTABILITY.md. For this delivery the due-list
        // report (ReportService) computes the window in PHP instead and
        // calls dueVaccinationsBetween() below, which is portable.
        $stmt->execute(['asof' => $asOfDate, 'window' => "+{$withinDays} days"]);
        return $stmt->fetchAll();
    }

    public static function dueVaccinationsBetween(string $from, string $to): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT v.*, a.animal_code, a.name AS animal_name
             FROM vaccinations v JOIN animals a ON a.id = v.animal_id
             WHERE v.next_due_date IS NOT NULL AND v.next_due_date BETWEEN :f AND :t
             ORDER BY v.next_due_date ASC'
        );
        $stmt->execute(['f' => $from, 't' => $to]);
        return $stmt->fetchAll();
    }

    public static function dispose(int $animalId, string $type, string $reason, ?int $userId): void
    {
        Database::transaction(function ($pdo) use ($animalId, $type, $reason, $userId) {
            $status = $type === 'death' ? 'deceased' : 'transferred';
            $pdo->prepare('UPDATE animals SET status = :s, disposed_at = :n, disposal_reason = :r, updated_at = :n WHERE id = :id')
                ->execute(['s' => $status, 'n' => date('Y-m-d H:i:s'), 'r' => $reason, 'id' => $animalId]);
            self::recordLifecycleEvent($animalId, 'disposal_' . $type, date('Y-m-d'), ['reason' => $reason], $userId);
        });
    }
}
