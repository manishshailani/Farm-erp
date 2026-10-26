<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Services\LivestockService;

return function (): void {
    echo "LivestockTest\n";

    $pdo = test_pdo();
    $goatSpeciesId = (int) $pdo->query("SELECT id FROM species WHERE name = 'Goat'")->fetchColumn();

    $animalId = null;

    T::run('Workflow #3: register a goat and assign an RFID tag', function () use (&$animalId, $goatSpeciesId) {
        $animalId = LivestockService::registerAnimal([
            'animal_code' => 'TEST-GOAT-1',
            'species_id' => (string) $goatSpeciesId,
            'breed_id' => '',
            'name' => 'Test Goat',
            'sex' => 'female',
            'date_of_birth' => '2024-01-01',
            'estimated_age_months' => '',
            'source' => 'born_on_farm',
            'purchase_date' => '',
            'purchase_cost' => '',
            'dam_id' => '',
            'sire_id' => '',
            'pen_location' => 'Pen 1',
            'current_weight_kg' => '15',
            'health_status' => 'healthy',
            'notes' => '',
        ], 1);
        T::assertTrue($animalId > 0, 'animal should be registered with an id');

        LivestockService::assignTag($animalId, 'TEST-TAG-001', 1);
        $tag = Animal::activeTag($animalId);
        T::assertSame('TEST-TAG-001', $tag, 'active tag must match what was assigned');
    });

    T::run('A tag already active on one animal cannot be assigned to another', function () use (&$animalId, $goatSpeciesId) {
        $otherId = LivestockService::registerAnimal([
            'animal_code' => 'TEST-GOAT-2', 'species_id' => (string) $goatSpeciesId, 'breed_id' => '',
            'name' => '', 'sex' => 'male', 'date_of_birth' => '', 'estimated_age_months' => '',
            'source' => 'born_on_farm', 'purchase_date' => '', 'purchase_cost' => '', 'dam_id' => '', 'sire_id' => '',
            'pen_location' => '', 'current_weight_kg' => '', 'health_status' => 'healthy', 'notes' => '',
        ], 1);

        T::assertThrows(
            fn () => LivestockService::assignTag($otherId, 'TEST-TAG-001', 1),
            'assigning an already-active tag number to a second animal must fail'
        );
    });

    T::run('Workflow #4: scan the tag and record a weight event', function () use (&$animalId) {
        $resolved = LivestockService::resolveTag('TEST-TAG-001', 1);
        T::assertNotNull($resolved, 'scanning a valid active tag must resolve to its animal');
        T::assertSame($animalId, (int) $resolved['id'], 'resolved animal must be the one the tag was assigned to');

        LivestockService::recordWeight($animalId, 17.5, date('Y-m-d H:i:s'), 'Monthly weighing', 1);
        $history = Animal::weightHistory($animalId);
        T::assertSame(2, count($history), 'initial registration weight + new weighing = 2 weight events');

        $animal = Animal::find($animalId);
        T::assertEqualsFloat(17.5, (float) $animal['current_weight_kg'], 'animals.current_weight_kg must reflect the latest weighing');
    });

    T::run('Scanning an unregistered tag returns no match but is still logged', function () {
        $resolved = LivestockService::resolveTag('NONEXISTENT-TAG', 1);
        T::assertNull($resolved, 'an unregistered tag must not resolve to an animal');

        $logCount = (int) test_pdo()
            ->query("SELECT COUNT(*) FROM rfid_scan_log WHERE tag_number = 'NONEXISTENT-TAG' AND matched = 0")
            ->fetchColumn();
        T::assertSame(1, $logCount, 'the unmatched scan attempt must still be recorded for traceability');
    });

    T::run('Workflow #5: record a vaccination and verify the next-due reminder', function () use (&$animalId) {
        LivestockService::recordVaccination([
            'animal_id' => $animalId,
            'vaccine_name' => 'PPR Vaccine',
            'target_disease' => 'PPR',
            'dose' => '1ml',
            'administered_date' => date('Y-m-d'),
            'batch_number' => 'B100',
            'expiry_date' => date('Y-m-d', strtotime('+6 months')),
            'administered_by' => 'Staff',
            'veterinarian' => '',
            'next_due_date' => date('Y-m-d', strtotime('+7 days')),
            'follow_up_notes' => '',
            'inventory_item_id' => '',
            'doses_consumed' => '',
        ], 1);

        $due = LivestockService::dueVaccinationsBetween(date('Y-m-d'), date('Y-m-d', strtotime('+14 days')));
        $found = array_filter($due, static fn ($row) => $row['animal_id'] === $animalId && $row['vaccine_name'] === 'PPR Vaccine');
        T::assertTrue(count($found) === 1, 'the vaccination just recorded must appear in the due-within-14-days report');
    });

    T::run('Workflow #6: register a birth / offspring with a dam reference', function () use (&$animalId, $goatSpeciesId) {
        $offspringId = LivestockService::registerAnimal([
            'animal_code' => 'TEST-GOAT-KID-1', 'species_id' => (string) $goatSpeciesId, 'breed_id' => '',
            'name' => 'Kid', 'sex' => 'female', 'date_of_birth' => date('Y-m-d'), 'estimated_age_months' => '0',
            'source' => 'born_on_farm', 'purchase_date' => '', 'purchase_cost' => '',
            'dam_id' => (string) $animalId, 'sire_id' => '',
            'pen_location' => '', 'current_weight_kg' => '2.1', 'health_status' => 'healthy', 'notes' => 'Newborn',
        ], 1);

        LivestockService::recordLifecycleEvent($animalId, 'birth_outcome', date('Y-m-d'), ['offspring_id' => $offspringId, 'count' => 1], 1);

        $offspring = Animal::find($offspringId);
        T::assertSame($animalId, (int) $offspring['dam_id'], 'offspring record must reference its dam');

        $events = Animal::lifecycleEvents($animalId);
        T::assertTrue(count($events) >= 1, 'dam must have a birth_outcome lifecycle event recorded');
    });

    T::run('Disposing an animal preserves its historical records', function () use (&$animalId) {
        LivestockService::dispose($animalId, 'death', 'Illness', 1);
        $animal = Animal::find($animalId);
        T::assertSame('deceased', $animal['status'], 'status must update to deceased');
        T::assertTrue(count(Animal::weightHistory($animalId)) >= 2, 'weight history must NOT be deleted on disposal');
        T::assertTrue(count(Animal::vaccinationHistory($animalId)) >= 1, 'vaccination history must NOT be deleted on disposal');
    });
};
