<?php
/** @var array $animals @var array $species @var array $filters */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Livestock</h2>
    <a href="<?= e($base) ?>/livestock/create" class="btn btn-success">+ Register animal</a>
</div>

<form class="row g-2 mb-3">
    <div class="col-auto">
        <select name="species_id" class="form-select">
            <option value="">All species</option>
            <?php foreach ($species as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= ((string) $filters['species_id'] === (string) $s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="status" class="form-select">
            <option value="">All statuses</option>
            <?php foreach (['active','reserved','sold','deceased','transferred'] as $st): ?>
                <option value="<?= $st ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><input name="q" value="<?= e($filters['q']) ?>" class="form-control" placeholder="Search code/name"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
</form>

<div class="card">
<table class="table mb-0">
    <thead><tr><th>Code</th><th>Species</th><th>Breed</th><th>Sex</th><th>Status</th><th>Weight</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($animals as $a): ?>
        <tr>
            <td><?= e($a['animal_code']) ?></td>
            <td><?= e($a['species_name']) ?></td>
            <td><?= e($a['breed_name'] ?? '-') ?></td>
            <td><?= e($a['sex']) ?></td>
            <td><span class="badge bg-secondary"><?= e($a['status']) ?></span></td>
            <td><?= $a['current_weight_kg'] !== null ? e($a['current_weight_kg']) . ' kg' : '-' ?></td>
            <td><a href="<?= e($base) ?>/livestock/<?= (int) $a['id'] ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($animals === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No animals found.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
