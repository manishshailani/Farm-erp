<?php
/** @var array $items @var array $categories @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="d-flex justify-content-between mb-3">
    <h2>Inventory</h2>
    <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#newItem">+ New item</button>
</div>

<div class="collapse mb-3" id="newItem">
    <div class="card p-3">
        <form method="post" action="<?= e($base) ?>/inventory" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-3"><input name="name" class="form-control" placeholder="Item name *" required></div>
            <div class="col-md-2"><input name="sku" class="form-control" placeholder="SKU (auto if blank)"></div>
            <div class="col-md-2">
                <select name="category_id" class="form-select">
                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input name="unit" class="form-control" placeholder="Unit (kg, dose...)" value="kg"></div>
            <div class="col-md-2"><input type="number" step="0.01" name="reorder_threshold" class="form-control" placeholder="Reorder threshold"></div>
            <div class="col-md-1"><button class="btn btn-success w-100">Add</button></div>
        </form>
    </div>
</div>

<div class="card">
<table class="table mb-0">
<thead><tr><th>Name</th><th>Category</th><th>Unit</th><th>Current qty</th><th>Reorder at</th><th>Avg cost</th><th></th></tr></thead>
<tbody>
<?php foreach ($items as $i): ?>
<tr class="<?= $i['current_quantity'] <= $i['reorder_threshold'] ? 'table-warning' : '' ?>">
    <td><?= e($i['name']) ?></td>
    <td><?= e($i['category_name'] ?? '-') ?></td>
    <td><?= e($i['unit']) ?></td>
    <td><?= e($i['current_quantity']) ?></td>
    <td><?= e($i['reorder_threshold']) ?></td>
    <td><?= money($i['average_cost']) ?></td>
    <td><a href="<?= e($base) ?>/inventory/<?= (int) $i['id'] ?>">Manage</a></td>
</tr>
<?php endforeach; ?>
<?php if ($items === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No inventory items yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
