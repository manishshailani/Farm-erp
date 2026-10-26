<?php
/** @var array $block @var array $trees @var array $harvests @var array $inventoryItems @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<p><a href="<?= e($base) ?>/orchard">&larr; All blocks</a></p>
<h2><?= e($block['name']) ?> <small class="text-muted"><?= e($block['species']) ?><?= $block['variety'] ? ' - ' . e($block['variety']) : '' ?></small></h2>
<?php if ($block['notes']): ?><p class="text-muted"><?= e($block['notes']) ?></p><?php endif; ?>

<div class="row">
<div class="col-md-6">
    <h5>Trees</h5>
    <div class="card p-3 mb-3">
        <form method="post" action="<?= e($base) ?>/orchard/<?= (int) $block['id'] ?>/trees" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-6"><input name="tree_code" class="form-control" placeholder="Tree code *" required></div>
            <div class="col-3"><input type="number" name="row_number" class="form-control" placeholder="Row"></div>
            <div class="col-3"><input type="number" name="position_in_row" class="form-control" placeholder="Pos"></div>
            <div class="col-6"><input type="date" name="planting_date" class="form-control"></div>
            <div class="col-6"><button class="btn btn-success w-100">Add tree</button></div>
        </form>
    </div>
    <div class="card">
    <table class="table mb-0">
    <thead><tr><th>Code</th><th>Row/Pos</th><th>Planted</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($trees as $t): ?>
    <tr>
        <td><?= e($t['tree_code']) ?></td>
        <td><?= e(($t['row_number'] ?? '-') . ' / ' . ($t['position_in_row'] ?? '-')) ?></td>
        <td><?= fdate($t['planting_date']) ?></td>
        <td><span class="badge bg-<?= $t['status'] === 'alive' ? 'success' : 'secondary' ?>"><?= e($t['status']) ?></span></td>
        <td>
            <form method="post" action="<?= e($base) ?>/orchard/<?= (int) $block['id'] ?>/trees/<?= (int) $t['id'] ?>/status" class="d-flex gap-1">
                <?= csrf_field() ?>
                <select name="status" class="form-select form-select-sm">
                    <option value="alive" <?= $t['status'] === 'alive' ? 'selected' : '' ?>>alive</option>
                    <option value="dead" <?= $t['status'] === 'dead' ? 'selected' : '' ?>>dead</option>
                    <option value="replaced" <?= $t['status'] === 'replaced' ? 'selected' : '' ?>>replaced</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary">Set</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if ($trees === []): ?><tr><td colspan="5" class="text-center text-muted py-3">No trees recorded in this block yet.</td></tr><?php endif; ?>
    </tbody>
    </table>
    </div>
</div>

<div class="col-md-6">
    <h5>Harvests</h5>
    <div class="card p-3 mb-3">
        <form method="post" action="<?= e($base) ?>/orchard/<?= (int) $block['id'] ?>/harvests" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-6"><input type="date" name="harvest_date" class="form-control" required></div>
            <div class="col-6"><input type="number" step="0.01" name="quantity_kg" class="form-control" placeholder="Quantity (kg) *" required></div>
            <div class="col-6">
                <select name="tree_id" class="form-select">
                    <option value="">Whole block (no specific tree)</option>
                    <?php foreach ($trees as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['tree_code']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6"><input name="grade" class="form-control" placeholder="Grade (optional)"></div>
            <div class="col-8">
                <select name="inventory_item_id" class="form-select">
                    <option value="">Do not credit to inventory</option>
                    <?php foreach ($inventoryItems as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e($i['name']) ?></option><?php endforeach; ?>
                </select>
                <div class="form-text">If selected, the harvested quantity is received into that inventory item at the same time.</div>
            </div>
            <div class="col-4"><input type="number" step="0.01" name="credit_unit_cost" class="form-control" placeholder="Credit unit cost (default 0)"></div>
            <div class="col-12"><button class="btn btn-success">Record harvest</button></div>
        </form>
    </div>
    <div class="card">
    <table class="table mb-0">
    <thead><tr><th>Date</th><th>Tree</th><th>Qty (kg)</th><th>Grade</th></tr></thead>
    <tbody>
    <?php foreach ($harvests as $h): ?>
    <tr>
        <td><?= fdate($h['harvest_date']) ?></td>
        <td><?= e($h['tree_code'] ?? 'Whole block') ?></td>
        <td><?= number_format((float) $h['quantity_kg'], 2) ?></td>
        <td><?= e($h['grade'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if ($harvests === []): ?><tr><td colspan="4" class="text-center text-muted py-3">No harvests recorded yet.</td></tr><?php endif; ?>
    </tbody>
    </table>
    </div>
</div>
</div>
