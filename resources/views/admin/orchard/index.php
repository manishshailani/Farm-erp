<?php
/** @var array $blocks @var array $totals @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="d-flex justify-content-between mb-3">
    <h2>Orchard</h2>
    <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#newBlock">+ New block</button>
</div>

<p class="text-muted">Total harvested to date: <strong><?= number_format($totals['total_kg'], 2) ?> kg</strong> across <?= (int) $totals['harvest_count'] ?> harvest events.</p>

<div class="collapse mb-3" id="newBlock">
    <div class="card p-3">
        <form method="post" action="<?= e($base) ?>/orchard/blocks" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-3"><input name="name" class="form-control" placeholder="Block name *" required></div>
            <div class="col-md-3"><input name="species" class="form-control" placeholder="Species (e.g. Mango) *" required></div>
            <div class="col-md-3"><input name="variety" class="form-control" placeholder="Variety (optional)"></div>
            <div class="col-md-2"><input name="notes" class="form-control" placeholder="Notes"></div>
            <div class="col-md-1"><button class="btn btn-success w-100">Add</button></div>
        </form>
    </div>
</div>

<div class="card">
<table class="table mb-0">
<thead><tr><th>Block</th><th>Species</th><th>Variety</th><th>Trees</th><th></th></tr></thead>
<tbody>
<?php foreach ($blocks as $b): ?>
<tr>
    <td><?= e($b['name']) ?></td>
    <td><?= e($b['species']) ?></td>
    <td><?= e($b['variety'] ?? '-') ?></td>
    <td><?= (int) $b['tree_count'] ?></td>
    <td><a href="<?= e($base) ?>/orchard/<?= (int) $b['id'] ?>">Manage</a></td>
</tr>
<?php endforeach; ?>
<?php if ($blocks === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No orchard blocks yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
