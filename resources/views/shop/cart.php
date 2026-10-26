<?php /** @var array $items */ use App\Core\Redirect; $base = Redirect::basePath();
$total = 0.0;
foreach ($items as $it) { $total += (float) ($it['unit_price_snapshot'] ?? 0) * (float) $it['quantity']; }
?>
<h2 class="mb-3">Your cart</h2>
<?php if ($items === []): ?>
    <p class="text-muted">Your cart is empty. <a href="<?= e($base) ?>/shop">Browse products</a></p>
<?php else: ?>
<div class="card">
<table class="table mb-0">
<thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th><th></th></tr></thead>
<?php foreach ($items as $it): ?>
<tr>
    <td><?= e($it['name']) ?></td>
    <td>
        <form method="post" action="<?= e($base) ?>/shop/cart/<?= (int) $it['id'] ?>/update" class="d-flex gap-1">
            <?= csrf_field() ?>
            <input type="number" step="0.01" name="quantity" value="<?= e($it['quantity']) ?>" class="form-control form-control-sm" style="width:80px">
            <button class="btn btn-sm btn-outline-secondary">Update</button>
        </form>
    </td>
    <td><?= $it['is_quotation_only'] ? 'Quote' : money((float) $it['unit_price_snapshot']) ?></td>
    <td><?= $it['is_quotation_only'] ? '-' : money((float) $it['unit_price_snapshot'] * (float) $it['quantity']) ?></td>
    <td>
        <form method="post" action="<?= e($base) ?>/shop/cart/<?= (int) $it['id'] ?>/remove">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger">Remove</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>
<p class="text-end mt-3 fs-5">Subtotal: <strong><?= money($total) ?></strong></p>
<a href="<?= e($base) ?>/shop/checkout" class="btn btn-success">Proceed to checkout</a>
<?php endif; ?>
