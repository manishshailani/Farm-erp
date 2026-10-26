<?php /** @var array $rows */ ?>
<h2 class="mb-3">Inventory stock levels <a class="btn btn-sm btn-outline-secondary" href="?format=csv">Export CSV</a></h2>
<div class="card"><table class="table mb-0">
<thead><tr><th>SKU</th><th>Name</th><th>Unit</th><th>Qty</th><th>Reorder at</th><th>Avg cost</th></tr></thead>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['sku']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['unit']) ?></td><td><?= e($r['current_quantity']) ?></td><td><?= e($r['reorder_threshold']) ?></td><td><?= money($r['average_cost']) ?></td></tr>
<?php endforeach; ?>
</table></div>
