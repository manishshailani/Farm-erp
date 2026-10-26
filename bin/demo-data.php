#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Populate a modest set of sample BUSINESS records (as opposed to the
 * reference/master data loaded by `php bin/console.php seed` -- roles,
 * species/breeds, inventory categories, and the small shop catalogue
 * already loaded by database/seeds/*.php) so a fresh install has
 * something to look at immediately: a few animals, two inventory items
 * with stock received, one orchard block with a tree and a harvest, and
 * one pond with a stocking batch.
 *
 * Deliberately NOT folded into `php bin/console.php seed`: seed is
 * documented as idempotent reference data safe to run on a production
 * database; this script creates fictitious operational records and must
 * never run there. It applies the exact same safety gates as
 * `reset-demo-data` for that reason.
 *
 * Explicitly out of scope for this script (kept small on purpose):
 * customers, a sample order, and employees/tasks. The shop's 3-product
 * catalogue from database/seeds/003_sample_catalogue.php already covers
 * the storefront being non-empty; adding a full customer+cart+order
 * flow here would need CartService/OrderService call shapes this pass
 * did not verify against -- better to ship a smaller, verified set than
 * guess at an API surface and risk inserting data that doesn't match
 * what those services actually expect.
 *
 * Usage:
 *   php bin/demo-data.php --i-understand-this-is-demo-only
 *
 * Idempotent: running it twice does not duplicate records (checks for
 * its own sentinel data first and exits cleanly if already present).
 */

require __DIR__ . '/../src/Core/Autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\InventoryService;
use App\Services\LivestockService;
use App\Services\OrchardService;
use App\Services\PondService;

Env::load(__DIR__ . '/../.env');
date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Asia/Kolkata'));

if (!in_array('--i-understand-this-is-demo-only', $argv, true)) {
    fwrite(STDERR, "Refusing to run: this inserts fictitious sample records (animals, inventory, orchard, pond).\n");
    fwrite(STDERR, "Re-run with --i-understand-this-is-demo-only to proceed.\n");
    fwrite(STDERR, "Never run this against a real production farm's database.\n");
    exit(1);
}
if ((string) Env::get('APP_ENV', 'production') === 'production') {
    fwrite(STDERR, "Refusing to run: APP_ENV is 'production' in .env. Demo data is for a dev/demo copy only.\n");
    exit(1);
}

$pdo = Database::connection();

$sentinel = $pdo->prepare('SELECT id FROM animals WHERE animal_code = :c');
$sentinel->execute(['c' => 'DEMO-GOAT-01']);
if ($sentinel->fetchColumn() !== false) {
    echo "Demo data already present (found DEMO-GOAT-01) -- nothing to do.\n";
    exit(0);
}

function species_id(\PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM species WHERE name = :n');
    $stmt->execute(['n' => $name]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        throw new RuntimeException("Species \"{$name}\" not found -- run 'php bin/console.php seed' first.");
    }
    return (int) $id;
}

function breed_id(\PDO $pdo, int $speciesId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM breeds WHERE species_id = :s AND name = :n');
    $stmt->execute(['s' => $speciesId, 'n' => $name]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        throw new RuntimeException("Breed \"{$name}\" not found -- run 'php bin/console.php seed' first.");
    }
    return (int) $id;
}

function category_id(\PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM inventory_categories WHERE name = :n');
    $stmt->execute(['n' => $name]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

echo "Creating demo animals...\n";
$goatSpecies = species_id($pdo, 'Goat');
$cattleSpecies = species_id($pdo, 'Cattle');

$animal1 = LivestockService::registerAnimal([
    'animal_code' => 'DEMO-GOAT-01',
    'species_id' => $goatSpecies,
    'breed_id' => breed_id($pdo, $goatSpecies, 'Jamunapari'),
    'name' => 'Ganga',
    'sex' => 'female',
    'date_of_birth' => '2024-03-10',
    'source' => 'born_on_farm',
    'pen_location' => 'Shed A',
    'current_weight_kg' => 32.5,
    'health_status' => 'healthy',
], null);
LivestockService::recordWeight($animal1, 32.5, '2024-03-10', 'Birth weight (demo data)', null);

$animal2 = LivestockService::registerAnimal([
    'animal_code' => 'DEMO-GOAT-02',
    'species_id' => $goatSpecies,
    'breed_id' => breed_id($pdo, $goatSpecies, 'Jamunapari'),
    'name' => 'Yamuna',
    'sex' => 'female',
    'date_of_birth' => '2024-05-02',
    'source' => 'born_on_farm',
    'pen_location' => 'Shed A',
    'current_weight_kg' => 28.0,
    'health_status' => 'healthy',
], null);

$animal3 = LivestockService::registerAnimal([
    'animal_code' => 'DEMO-CATTLE-01',
    'species_id' => $cattleSpecies,
    'breed_id' => breed_id($pdo, $cattleSpecies, 'Sahiwal'),
    'name' => 'Lakshmi',
    'sex' => 'female',
    'date_of_birth' => '2021-01-15',
    'source' => 'purchased',
    'purchase_date' => '2022-02-01',
    'purchase_cost' => 45000,
    'pen_location' => 'Shed B',
    'current_weight_kg' => 310.0,
    'health_status' => 'healthy',
], null);
echo "  Registered animals #{$animal1}, #{$animal2}, #{$animal3}.\n";

echo "Creating demo inventory items with stock...\n";
$now = date('Y-m-d');
$insertItem = $pdo->prepare(
    'INSERT INTO inventory_items (sku, name, category_id, unit, reorder_threshold, current_quantity, average_cost, storage_location, allow_negative_stock, is_active, created_at, updated_at)
     VALUES (:sku, :name, :cat, :unit, :threshold, 0, 0, :loc, 0, 1, :c, :u)'
);
$nowDt = date('Y-m-d H:i:s');
$insertItem->execute([
    'sku' => 'DEMO-FEED-01', 'name' => 'Goat Feed Mix (Demo)', 'cat' => category_id($pdo, 'Animal Feed'),
    'unit' => 'kg', 'threshold' => 20, 'loc' => 'Store Room 1', 'c' => $nowDt, 'u' => $nowDt,
]);
$feedItemId = (int) Database::lastInsertId();
InventoryService::receiveStock($feedItemId, 100, 28.50, $now, 'DEMO-BATCH-001', 'Demo Feed Supplier', null, null);

$insertItem->execute([
    'sku' => 'DEMO-VACCINE-01', 'name' => 'FMD Vaccine (Demo)', 'cat' => category_id($pdo, 'Vaccines'),
    'unit' => 'dose', 'threshold' => 10, 'loc' => 'Cold Storage', 'c' => $nowDt, 'u' => $nowDt,
]);
$vaccineItemId = (int) Database::lastInsertId();
InventoryService::receiveStock($vaccineItemId, 50, 45.00, $now, 'DEMO-BATCH-002', 'Demo Veterinary Supplies', '2026-12-31', null);
echo "  Created items #{$feedItemId} (Goat Feed Mix) and #{$vaccineItemId} (FMD Vaccine), both stocked.\n";

echo "Creating demo orchard block, tree and harvest...\n";
$blockId = OrchardService::createBlock([
    'name' => 'North Mango Block (Demo)',
    'species' => 'Mango',
    'variety' => 'Dashehari',
    'notes' => 'Sample data created by bin/demo-data.php',
], null);
$treeId = OrchardService::plantTree([
    'block_id' => $blockId,
    'tree_code' => 'DEMO-TREE-001',
    'row_number' => 1,
    'position_in_row' => 1,
    'planting_date' => '2018-07-01',
], null);
OrchardService::recordHarvest([
    'block_id' => $blockId,
    'tree_id' => $treeId,
    'harvest_date' => $now,
    'quantity_kg' => 12.5,
    'grade' => 'A',
], null);
echo "  Created orchard block #{$blockId} with tree #{$treeId} and one harvest record.\n";

echo "Creating demo pond and stocking batch...\n";
$pondId = PondService::createPond([
    'name' => 'Demo Pond 1',
    'surface_area_sqm' => 500,
    'estimated_depth_m' => 1.8,
    'water_source' => 'borewell',
    'notes' => 'Sample data created by bin/demo-data.php',
], null);
$batchId = PondService::addStockingBatch([
    'pond_id' => $pondId,
    'species' => 'Rohu',
    'stocking_date' => '2026-06-01',
    'fingerling_quantity' => 2000,
    'average_stocking_weight_g' => 5,
    'purchase_price' => 4000,
], null);
echo "  Created pond #{$pondId} with stocking batch #{$batchId}.\n";

echo "\nDemo data created. The shop storefront already has 3 sample products from\n";
echo "the regular seed step; animals, inventory, orchard and pond now have sample\n";
echo "records too. No sample customer or order was created -- see this script's\n";
echo "docblock for why that is explicitly out of scope.\n";
