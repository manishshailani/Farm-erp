<?php
/** @var array $animal @var ?string $activeTag @var array $weightHistory @var array $lifecycleEvents
 *  @var array $vaccinations @var array $inventoryItems @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h2><?= e($animal['animal_code']) ?> <small class="text-muted"><?= e($animal['name'] ?? '') ?></small></h2>
        <span class="badge bg-secondary"><?= e($animal['status']) ?></span>
        <span class="badge bg-info text-dark"><?= e($animal['species_name']) ?><?= $animal['breed_name'] ? ' / ' . e($animal['breed_name']) : '' ?></span>
        <?php if ($activeTag): ?><span class="badge bg-success">RFID: <?= e($activeTag) ?></span><?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card p-3 mb-3">
            <h6>Details</h6>
            <table class="table table-sm mb-0">
                <tr><th>Sex</th><td><?= e($animal['sex']) ?></td></tr>
                <tr><th>DOB</th><td><?= fdate($animal['date_of_birth']) ?></td></tr>
                <tr><th>Source</th><td><?= e($animal['source']) ?></td></tr>
                <tr><th>Purchase cost</th><td><?= $animal['purchase_cost'] !== null ? money($animal['purchase_cost']) : '-' ?></td></tr>
                <tr><th>Pen</th><td><?= e($animal['pen_location'] ?? '-') ?></td></tr>
                <tr><th>Current weight</th><td><?= $animal['current_weight_kg'] !== null ? e($animal['current_weight_kg']) . ' kg' : '-' ?></td></tr>
                <tr><th>Health</th><td><?= e($animal['health_status']) ?></td></tr>
            </table>
        </div>

        <?php if (!$activeTag): ?>
        <div class="card p-3 mb-3">
            <h6>Assign RFID tag</h6>
            <form method="post" action="<?= e($base) ?>/livestock/<?= (int) $animal['id'] ?>/tag">
                <?= csrf_field() ?>
                <input name="tag_number" class="form-control mb-2" placeholder="Scan or type tag number" required autofocus>
                <button class="btn btn-sm btn-outline-success">Assign tag</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card p-3 mb-3">
            <h6>Record weight</h6>
            <form method="post" action="<?= e($base) ?>/livestock/<?= (int) $animal['id'] ?>/weight">
                <?= csrf_field() ?>
                <input type="number" step="0.01" name="weight_kg" class="form-control mb-2" placeholder="Weight (kg)" required>
                <input name="notes" class="form-control mb-2" placeholder="Notes (optional)">
                <button class="btn btn-sm btn-outline-primary">Save weight</button>
            </form>
        </div>

        <?php if ($animal['status'] === 'active' || $animal['status'] === 'reserved'): ?>
        <div class="card p-3">
            <h6>Dispose / mark deceased</h6>
            <form method="post" action="<?= e($base) ?>/livestock/<?= (int) $animal['id'] ?>/dispose">
                <?= csrf_field() ?>
                <select name="disposal_type" class="form-select mb-2">
                    <option value="death">Death</option>
                    <option value="transfer">Transfer out</option>
                </select>
                <input name="reason" class="form-control mb-2" placeholder="Reason">
                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?');">Record</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-8">
        <div class="card p-3 mb-3">
            <h6>Record vaccination / treatment</h6>
            <form method="post" action="<?= e($base) ?>/livestock/<?= (int) $animal['id'] ?>/vaccination" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-md-4"><input name="vaccine_name" class="form-control" placeholder="Vaccine / treatment *" required></div>
                <div class="col-md-4"><input name="target_disease" class="form-control" placeholder="Target disease"></div>
                <div class="col-md-4"><input type="date" name="administered_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-4"><input name="dose" class="form-control" placeholder="Dose"></div>
                <div class="col-md-4"><input name="veterinarian" class="form-control" placeholder="Veterinarian"></div>
                <div class="col-md-4"><input type="date" name="next_due_date" class="form-control" placeholder="Next due date"></div>
                <div class="col-md-6">
                    <select name="inventory_item_id" class="form-select">
                        <option value="">No inventory deduction</option>
                        <?php foreach ($inventoryItems as $item): ?>
                            <option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?> (<?= e($item['current_quantity']) ?> <?= e($item['unit']) ?> left)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><input type="number" step="0.01" name="doses_consumed" class="form-control" placeholder="Qty consumed"></div>
                <div class="col-md-3"><button class="btn btn-outline-success w-100">Save</button></div>
            </form>
        </div>

        <div class="card p-3 mb-3">
            <h6>Vaccination history</h6>
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Vaccine</th><th>Disease</th><th>Next due</th></tr></thead>
                <tbody>
                <?php foreach ($vaccinations as $row): ?>
                    <tr><td><?= fdate($row['administered_date']) ?></td><td><?= e($row['vaccine_name']) ?></td><td><?= e($row['target_disease'] ?? '-') ?></td><td><?= fdate($row['next_due_date']) ?></td></tr>
                <?php endforeach; ?>
                <?php if ($vaccinations === []): ?><tr><td colspan="4" class="text-muted">No records yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card p-3">
            <h6>Weight history</h6>
            <table class="table table-sm">
                <?php foreach ($weightHistory as $row): ?>
                    <tr><td><?= fdate($row['recorded_at'], 'd-m-Y H:i') ?></td><td><?= e($row['weight_kg']) ?> kg</td><td class="text-muted"><?= e($row['notes'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                <?php if ($weightHistory === []): ?><tr><td class="text-muted">No weight records yet.</td></tr><?php endif; ?>
            </table>
        </div>
    </div>
</div>
