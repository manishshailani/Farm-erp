<?php /** @var array $items @var array $addresses */ use App\Core\Redirect; $base = Redirect::basePath();
$total = 0.0;
foreach ($items as $it) { $total += (float) ($it['unit_price_snapshot'] ?? 0) * (float) $it['quantity']; }
?>
<h2 class="mb-3">Checkout</h2>
<div class="row g-3">
    <div class="col-md-7">
        <div class="card p-3">
            <h6>Order summary</h6>
            <table class="table table-sm">
                <?php foreach ($items as $it): ?>
                    <tr><td><?= e($it['name']) ?> &times; <?= e($it['quantity']) ?></td><td class="text-end"><?= $it['is_quotation_only'] ? 'Quote' : money((float) $it['unit_price_snapshot'] * (float) $it['quantity']) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <p class="text-end">Subtotal: <strong><?= money($total) ?></strong></p>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card p-3">
            <h6>Delivery</h6>
            <form method="post" action="<?= e($base) ?>/shop/checkout">
                <?= csrf_field() ?>
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="delivery_method" value="pickup" checked><label class="form-check-label">Farm pickup (free)</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="delivery_method" value="delivery"><label class="form-check-label">Home delivery (₹50)</label></div>
                <?php if ($addresses !== []): ?>
                <select name="address_id" class="form-select mb-2">
                    <?php foreach ($addresses as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['label']) ?>: <?= e($a['line1']) ?>, <?= e($a['city']) ?></option><?php endforeach; ?>
                </select>
                <?php else: ?>
                    <p class="small text-muted">No saved address. <a href="<?= e($base) ?>/shop/account/addresses">Add one</a> before choosing delivery.</p>
                <?php endif; ?>
                <textarea name="notes" class="form-control mb-2" placeholder="Order notes (optional)"></textarea>
                <button class="btn btn-success w-100">Place order</button>
            </form>
        </div>
    </div>
</div>
