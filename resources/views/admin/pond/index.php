<?php
/** @var array $ponds @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="d-flex justify-content-between mb-3">
    <h2>Ponds</h2>
    <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#newPond">+ New pond</button>
</div>

<div class="collapse mb-3" id="newPond">
    <div class="card p-3">
        <form method="post" action="<?= e($base) ?>/pond/ponds" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-3"><input name="name" class="form-control" placeholder="Pond name *" required></div>
            <div class="col-md-2"><input type="number" step="0.01" name="surface_area_sqm" class="form-control" placeholder="Area (sqm)"></div>
            <div class="col-md-2"><input type="number" step="0.01" name="estimated_depth_m" class="form-control" placeholder="Depth (m)"></div>
            <div class="col-md-3"><input name="water_source" class="form-control" placeholder="Water source"></div>
            <div class="col-md-2"><button class="btn btn-success w-100">Add</button></div>
        </form>
    </div>
</div>

<div class="card">
<table class="table mb-0">
<thead><tr><th>Pond</th><th>Area (sqm)</th><th>Depth (m)</th><th>Active batches</th><th></th></tr></thead>
<tbody>
<?php foreach ($ponds as $p): ?>
<tr>
    <td><?= e($p['name']) ?></td>
    <td><?= e($p['surface_area_sqm'] ?? '-') ?></td>
    <td><?= e($p['estimated_depth_m'] ?? '-') ?></td>
    <td><?= (int) $p['active_batches'] ?></td>
    <td><a href="<?= e($base) ?>/pond/<?= (int) $p['id'] ?>">Manage</a></td>
</tr>
<?php endforeach; ?>
<?php if ($ponds === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No ponds yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
