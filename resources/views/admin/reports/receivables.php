<?php /** @var array $rows */ ?>
<h2 class="mb-3">Customer receivables <a class="btn btn-sm btn-outline-secondary" href="?format=csv">Export CSV</a></h2>
<div class="card"><table class="table mb-0">
<thead><tr><th>Customer</th><th>Invoice</th><th>Amount</th><th>Paid</th><th>Balance</th></tr></thead>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['customer_name']) ?></td><td><?= e($r['invoice_number']) ?></td><td><?= money($r['amount']) ?></td><td><?= money($r['amount_paid']) ?></td><td><?= money($r['balance']) ?></td></tr>
<?php endforeach; ?>
<?php if ($rows === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No outstanding receivables.</td></tr><?php endif; ?>
</table></div>
