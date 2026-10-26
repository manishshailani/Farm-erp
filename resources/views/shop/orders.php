<?php /** @var array $orders */ use App\Core\Redirect; $base = Redirect::basePath(); ?>
<h2 class="mb-3">My orders</h2>
<div class="card">
<table class="table mb-0">
<thead><tr><th>Order #</th><th>Status</th><th>Total</th><th>Placed</th><th></th></tr></thead>
<?php foreach ($orders as $o): ?>
<tr>
    <td><?= e($o['order_number']) ?></td>
    <td><span class="badge bg-secondary"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
    <td><?= money($o['total_amount']) ?></td>
    <td><?= fdate($o['placed_at'], 'd-m-Y H:i') ?></td>
    <td><a href="<?= e($base) ?>/shop/account/orders/<?= (int) $o['id'] ?>">View</a></td>
</tr>
<?php endforeach; ?>
<?php if ($orders === []): ?><tr><td colspan="5" class="text-center text-muted py-4">You have no orders yet.</td></tr><?php endif; ?>
</table>
</div>
