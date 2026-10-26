<?php
/** @var array $order @var array $items @var array $history @var ?array $invoice @var array $validTransitions
 *  @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<h2>Order <?= e($order['order_number']) ?></h2>
<span class="badge bg-secondary fs-6"><?= e(str_replace('_', ' ', $order['status'])) ?></span>

<div class="row g-3 mt-3">
    <div class="col-md-7">
        <div class="card p-3 mb-3">
            <h6>Items</h6>
            <table class="table table-sm">
                <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                <?php foreach ($items as $i): ?>
                    <tr><td><?= e($i['product_name_snapshot']) ?></td><td><?= e($i['quantity']) ?></td><td><?= money($i['unit_price']) ?></td><td><?= money($i['line_total']) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <p class="text-end mb-0">Subtotal: <?= money($order['subtotal']) ?> &nbsp; Delivery: <?= money($order['delivery_charge']) ?> &nbsp; <strong>Total: <?= money($order['total_amount']) ?></strong></p>
        </div>

        <div class="card p-3">
            <h6>Status history</h6>
            <ul class="list-unstyled small mb-0">
                <?php foreach ($history as $h): ?>
                    <li><?= fdate($h['changed_at'], 'd-m-Y H:i') ?> — <?= e(str_replace('_', ' ', $h['status'])) ?> <?= $h['changed_by_name'] ? 'by ' . e($h['changed_by_name']) : '' ?><?= $h['notes'] ? ' — ' . e($h['notes']) : '' ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-md-5">
        <?php if ($validTransitions !== []): ?>
        <div class="card p-3 mb-3">
            <h6>Update status</h6>
            <form method="post" action="<?= e($base) ?>/orders/<?= (int) $order['id'] ?>/transition">
                <?= csrf_field() ?>
                <select name="status" class="form-select mb-2">
                    <?php foreach ($validTransitions as $t): ?><option value="<?= e($t) ?>"><?= e(str_replace('_', ' ', $t)) ?></option><?php endforeach; ?>
                </select>
                <input name="notes" class="form-control mb-2" placeholder="Notes (optional)">
                <button class="btn btn-success w-100" onclick="return confirm('Change order status?');">Update</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($invoice !== null): ?>
        <div class="card p-3">
            <h6>Invoice <?= e($invoice['invoice_number']) ?></h6>
            <p>Amount: <?= money($invoice['amount']) ?> &nbsp; Paid: <?= money($invoice['amount_paid']) ?></p>
            <span class="badge bg-info text-dark mb-2"><?= e($invoice['status']) ?></span>
            <?php if ($invoice['status'] !== 'paid'): ?>
            <form method="post" action="<?= e($base) ?>/orders/<?= (int) $order['id'] ?>/payments">
                <?= csrf_field() ?>
                <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
                <input type="number" step="0.01" name="amount" class="form-control mb-2" placeholder="Amount received *" required>
                <select name="method" class="form-select mb-2">
                    <option value="cash">Cash</option>
                    <option value="upi">UPI</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="cod">Cash on delivery</option>
                </select>
                <input name="reference" class="form-control mb-2" placeholder="Reference (optional)">
                <button class="btn btn-success w-100">Record payment</button>
            </form>
            <?php else: ?>
            <form method="post" action="<?= e($base) ?>/orders/<?= (int) $order['id'] ?>/payments">
                <?= csrf_field() ?>
                <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
                <input type="hidden" name="is_refund" value="1">
                <input type="number" step="0.01" name="amount" class="form-control mb-2" placeholder="Refund amount *" required>
                <select name="method" class="form-select mb-2">
                    <option value="cash">Cash</option>
                    <option value="upi">UPI</option>
                    <option value="bank_transfer">Bank transfer</option>
                </select>
                <button class="btn btn-outline-danger w-100">Record refund</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
