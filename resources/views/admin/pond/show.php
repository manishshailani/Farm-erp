<?php
/** @var array $pond @var array $batches @var array $readings @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<p><a href="<?= e($base) ?>/pond">&larr; All ponds</a></p>
<h2><?= e($pond['name']) ?></h2>
<p class="text-muted">
    Area: <?= e($pond['surface_area_sqm'] ?? '-') ?> sqm &middot;
    Depth: <?= e($pond['estimated_depth_m'] ?? '-') ?> m &middot;
    Source: <?= e($pond['water_source'] ?? '-') ?>
</p>

<div class="row">
<div class="col-md-7">
    <h5>Stocking batches</h5>
    <div class="card p-3 mb-3">
        <form method="post" action="<?= e($base) ?>/pond/<?= (int) $pond['id'] ?>/stocking" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-3"><input name="species" class="form-control" placeholder="Species *" required></div>
            <div class="col-md-3"><input type="date" name="stocking_date" class="form-control" required></div>
            <div class="col-md-2"><input type="number" name="fingerling_quantity" class="form-control" placeholder="Qty *" required></div>
            <div class="col-md-2"><input type="number" step="0.01" name="average_stocking_weight_g" class="form-control" placeholder="Avg wt (g)"></div>
            <div class="col-md-2"><input type="number" step="0.01" name="purchase_price" class="form-control" placeholder="Cost"></div>
            <div class="col-md-12"><button class="btn btn-success">Add stocking batch</button></div>
        </form>
    </div>
    <div class="card">
    <table class="table mb-0">
    <thead><tr><th>Species</th><th>Stocked</th><th>Qty</th><th>Status</th><th>Harvest</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($batches as $b): ?>
    <tr>
        <td><?= e($b['species']) ?></td>
        <td><?= fdate($b['stocking_date']) ?></td>
        <td><?= (int) $b['fingerling_quantity'] ?></td>
        <td><span class="badge bg-<?= $b['status'] === 'growing' ? 'success' : ($b['status'] === 'harvested' ? 'primary' : 'secondary') ?>"><?= e($b['status']) ?></span></td>
        <td><?= $b['harvest_quantity_kg'] !== null ? number_format((float) $b['harvest_quantity_kg'], 2) . ' kg' : '-' ?></td>
        <td>
            <?php if ($b['status'] === 'growing'): ?>
            <div class="d-flex gap-1">
                <form method="post" action="<?= e($base) ?>/pond/<?= (int) $pond['id'] ?>/stocking/<?= (int) $b['id'] ?>/harvest" class="d-flex gap-1">
                    <?= csrf_field() ?>
                    <input type="date" name="harvest_date" class="form-control form-control-sm" required>
                    <input type="number" step="0.01" name="harvest_quantity_kg" class="form-control form-control-sm" placeholder="kg" required style="width:70px">
                    <input type="number" step="0.01" name="sale_amount" class="form-control form-control-sm" placeholder="₹ sale" style="width:80px">
                    <button class="btn btn-sm btn-primary">Harvest</button>
                </form>
                <form method="post" action="<?= e($base) ?>/pond/<?= (int) $pond['id'] ?>/stocking/<?= (int) $b['id'] ?>/lost">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger">Mark lost</button>
                </form>
            </div>
            <?php else: ?>
                <?= $b['sale_amount'] !== null ? money($b['sale_amount']) : '-' ?>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if ($batches === []): ?><tr><td colspan="6" class="text-center text-muted py-3">No stocking batches yet.</td></tr><?php endif; ?>
    </tbody>
    </table>
    </div>
</div>

<div class="col-md-5">
    <h5>Water quality readings</h5>
    <div class="card p-3 mb-3">
        <form method="post" action="<?= e($base) ?>/pond/<?= (int) $pond['id'] ?>/water-quality" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-12"><input type="datetime-local" name="measured_at" class="form-control" required></div>
            <div class="col-6"><input type="number" step="0.01" name="temperature_c" class="form-control" placeholder="Temp (C)"></div>
            <div class="col-6"><input type="number" step="0.01" name="dissolved_oxygen_mgl" class="form-control" placeholder="DO (mg/L)"></div>
            <div class="col-6"><input type="number" step="0.01" name="ph" class="form-control" placeholder="pH"></div>
            <div class="col-6"><input type="number" step="0.001" name="ammonia_mgl" class="form-control" placeholder="Ammonia (mg/L)"></div>
            <div class="col-12"><button class="btn btn-success w-100">Record reading</button></div>
        </form>
    </div>
    <div class="card">
    <table class="table table-sm mb-0">
    <thead><tr><th>When</th><th>Temp</th><th>DO</th><th>pH</th><th>NH3</th></tr></thead>
    <tbody>
    <?php foreach ($readings as $r): ?>
    <tr>
        <td><?= e($r['measured_at']) ?></td>
        <td><?= e($r['temperature_c'] ?? '-') ?></td>
        <td><?= e($r['dissolved_oxygen_mgl'] ?? '-') ?></td>
        <td><?= e($r['ph'] ?? '-') ?></td>
        <td><?= e($r['ammonia_mgl'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if ($readings === []): ?><tr><td colspan="5" class="text-center text-muted py-3">No readings recorded yet.</td></tr><?php endif; ?>
    </tbody>
    </table>
    </div>
</div>
</div>
