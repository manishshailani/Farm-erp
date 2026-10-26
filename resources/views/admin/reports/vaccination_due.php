<?php /** @var array $rows @var string $from @var string $to */ ?>
<h2 class="mb-3">Vaccination due/overdue (<?= fdate($from) ?> to <?= fdate($to) ?>) <a class="btn btn-sm btn-outline-secondary" href="?format=csv&from=<?= e($from) ?>&to=<?= e($to) ?>">Export CSV</a></h2>
<div class="card"><table class="table mb-0">
<thead><tr><th>Animal</th><th>Vaccine</th><th>Next due</th></tr></thead>
<?php foreach ($rows as $r): ?>
<tr><td><?= e($r['animal_code']) ?></td><td><?= e($r['vaccine_name']) ?></td><td><?= fdate($r['next_due_date']) ?></td></tr>
<?php endforeach; ?>
<?php if ($rows === []): ?><tr><td colspan="3" class="text-center text-muted py-4">Nothing due in this range.</td></tr><?php endif; ?>
</table></div>
