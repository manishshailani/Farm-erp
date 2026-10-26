<?php /** @var array $rows @var array $summary @var string $from @var string $to */ ?>
<h2 class="mb-3">Income &amp; expense (<?= fdate($from) ?> to <?= fdate($to) ?>) <a class="btn btn-sm btn-outline-secondary" href="?format=csv&from=<?= e($from) ?>&to=<?= e($to) ?>">Export CSV</a></h2>
<p>Income: <strong class="text-success"><?= money($summary['income']) ?></strong> &nbsp; Expense: <strong class="text-danger"><?= money($summary['expense']) ?></strong> &nbsp; Net: <strong><?= money($summary['net']) ?></strong></p>
<div class="card"><table class="table mb-0">
<thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th></tr></thead>
<?php foreach ($rows as $r): ?>
<tr><td><?= fdate($r['txn_date']) ?></td><td><?= e($r['type']) ?></td><td><?= e($r['category']) ?></td><td><?= money($r['amount']) ?></td></tr>
<?php endforeach; ?>
</table></div>
