<?php
/** @var array $orders @var string $statusFilter */
use App\Core\Redirect;
$base = Redirect::basePath();
$statuses = ['', 'placed', 'awaiting_quotation_approval', 'confirmed', 'awaiting_payment', 'partially_paid', 'processing', 'ready_for_dispatch', 'dispatched', 'delivered', 'completed', 'cancelled', 'refund_pending', 'refunded', 'returned'];
?>
<div class="d-flex justify-content-between mb-3">
    <h2>Orders</h2>
    <form class="d-flex gap-2">
        <select name="status" class="form-select" onchange="this.form.submit()">
            <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s === '' ? 'All statuses' : str_replace('_', ' ', ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<div class="card">
<table class="table mb-0">
<thead><tr><th>Order #</th><th>Customer</th><th>Status</th><th>Total</th><th>Placed</th><th></th></tr></thead>
<?php foreach ($orders as $o): ?>
<tr>
    <td><?= e($o['order_number']) ?></td>
    <td><?= e($o['customer_name']) ?></td>
    <td><span class="badge bg-secondary"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
    <td><?= money($o['total_amount']) ?></td>
    <td><?= fdate($o['placed_at'], 'd-m-Y H:i') ?></td>
    <td><a href="<?= e($base) ?>/orders/<?= (int) $o['id'] ?>">View</a></td>
</tr>
<?php endforeach; ?>
<?php if ($orders === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No orders found.</td></tr><?php endif; ?>
</table>
</div>
