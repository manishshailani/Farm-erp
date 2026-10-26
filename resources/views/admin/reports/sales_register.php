<?php /** @var array $rows @var string $from @var string $to */ ?>
<h2 class="mb-3">Sales register (<?= fdate($from) ?> to <?= fdate($to) ?>) <a class="btn btn-sm btn-outline-secondary" href="?format=csv&from=<?= e($from) ?>&to=<?= e($to) ?>">Export CSV</a></h2>
<div class="card"><table class="table mb-0">
<thead><tr><th>Order #</th><th>Customer</th><th>Status</th><th>Total</th><th>Placed</th></tr></thead>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['order_number']) ?></td><td><?= e($r['customer_name']) ?></td><td><?= e($r['status']) ?></td><td><?= money($r['total_amount']) ?></td><td><?= fdate($r['placed_at'], 'd-m-Y H:i') ?></td></tr>
<?php endforeach; ?>
<?php if ($rows === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No orders in this period.</td></tr><?php endif; ?>
</table></div>
