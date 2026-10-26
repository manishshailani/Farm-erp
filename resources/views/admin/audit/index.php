<?php
/** @var array $logs @var array $entityTypes @var array $filters */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<h2 class="mb-3">Audit Log</h2>
<p class="text-muted">Every create/update/status-change recorded by the system, oldest action never edited or deleted. Showing the most recent <?= count($logs) ?> entries.</p>

<form method="get" action="<?= e($base) ?>/audit" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="entity_type" class="form-select">
            <option value="">All record types</option>
            <?php foreach ($entityTypes as $t): ?>
                <option value="<?= e($t) ?>" <?= $filters['entity_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <input name="q" class="form-control" placeholder="Search action / notes" value="<?= e($filters['q']) ?>">
    </div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>

<div class="card">
<table class="table table-sm mb-0">
<thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Notes</th><th>IP</th></tr></thead>
<tbody>
<?php foreach ($logs as $l): ?>
<tr>
    <td><?= e($l['created_at']) ?></td>
    <td><?= e($l['user_name'] ?? 'system') ?></td>
    <td><code><?= e($l['action']) ?></code></td>
    <td><?= e($l['entity_type']) ?><?= $l['entity_id'] !== null ? ' #' . (int) $l['entity_id'] : '' ?></td>
    <td class="text-muted small"><?= e($l['notes'] ?? '') ?></td>
    <td class="text-muted small"><?= e($l['ip_address'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
<?php if ($logs === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No audit entries match this filter.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
