<?php
/** @var array $item @var array $batches @var array $movements @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<h2><?= e($item['name']) ?></h2>
<p class="text-muted">SKU <?= e($item['sku']) ?> — Current stock: <strong><?= e($item['current_quantity']) ?> <?= e($item['unit']) ?></strong> — Avg cost <?= money($item['average_cost']) ?>/<?= e($item['unit']) ?></p>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card p-3 mb-3">
            <h6>Receive stock (purchase)</h6>
            <form method="post" action="<?= e($base) ?>/inventory/<?= (int) $item['id'] ?>/receive">
                <?= csrf_field() ?>
                <div class="mb-2"><input type="number" step="0.01" name="quantity" class="form-control" placeholder="Quantity *" required></div>
                <div class="mb-2"><input type="number" step="0.0001" name="unit_cost" class="form-control" placeholder="Unit cost (₹) *" required></div>
                <div class="mb-2"><input type="date" name="received_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                <div class="mb-2"><input name="batch_number" class="form-control" placeholder="Batch number"></div>
                <div class="mb-2"><input name="supplier_name" class="form-control" placeholder="Supplier"></div>
                <div class="mb-2"><input type="date" name="expiry_date" class="form-control" placeholder="Expiry date"></div>
                <button class="btn btn-success">Receive</button>
            </form>
        </div>
        <div class="card p-3">
            <h6>Manual adjustment</h6>
            <form method="post" action="<?= e($base) ?>/inventory/<?= (int) $item['id'] ?>/adjust">
                <?= csrf_field() ?>
                <div class="mb-2"><input type="number" step="0.01" name="new_quantity" class="form-control" placeholder="Correct quantity to" required></div>
                <div class="mb-2"><input name="reason" class="form-control" placeholder="Reason *" required></div>
                <button class="btn btn-outline-warning">Adjust</button>
            </form>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card p-3 mb-3">
            <h6>Batches</h6>
            <table class="table table-sm">
                <thead><tr><th>Batch</th><th>Received</th><th>Remaining</th><th>Unit cost</th><th>Expiry</th></tr></thead>
                <?php foreach ($batches as $b): ?>
                    <tr><td><?= e($b['batch_number'] ?? '-') ?></td><td><?= fdate($b['received_date']) ?></td><td><?= e($b['quantity_remaining']) ?></td><td><?= money($b['unit_cost']) ?></td><td><?= fdate($b['expiry_date']) ?></td></tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="card p-3">
            <h6>Recent stock movements</h6>
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Type</th><th>Qty</th><th>Reference</th></tr></thead>
                <?php foreach ($movements as $m): ?>
                    <tr><td><?= fdate($m['created_at'], 'd-m-Y H:i') ?></td><td><?= e($m['movement_type']) ?></td><td><?= e($m['quantity']) ?></td><td class="small text-muted"><?= e($m['reference_type'] ?? '-') ?> <?= $m['notes'] ? '— ' . e($m['notes']) : '' ?></td></tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
