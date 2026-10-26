<?php /** @var array $rows */ ?>
<h2 class="mb-3">Livestock register <a class="btn btn-sm btn-outline-secondary" href="?format=csv">Export CSV</a></h2>
<div class="card"><table class="table mb-0">
<thead><tr><th>Code</th><th>Species</th><th>Breed</th><th>Sex</th><th>DOB</th><th>Status</th><th>Weight</th></tr></thead>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['animal_code']) ?></td><td><?= e($r['species']) ?></td><td><?= e($r['breed'] ?? '-') ?></td><td><?= e($r['sex']) ?></td><td><?= fdate($r['date_of_birth']) ?></td><td><?= e($r['status']) ?></td><td><?= e($r['current_weight_kg'] ?? '-') ?></td></tr>
<?php endforeach; ?>
<?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No data.</td></tr><?php endif; ?>
</table></div>
