<?php
/** @var array $animalsBySpecies @var array $dueVaccinations @var array $overdueVaccinations
 *  @var array $lowStock @var int $ordersAwaitingAction @var array $financeSummary
 *  @var float $outstandingReceivables @var int $tasksToday */
?>
<h2 class="mb-4">Dashboard</h2>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Orders awaiting action</div><div class="fs-3 fw-bold"><?= (int) $ordersAwaitingAction ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-muted small">This month's income</div><div class="fs-3 fw-bold text-success"><?= money($financeSummary['income']) ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-muted small">This month's expense</div><div class="fs-3 fw-bold text-danger"><?= money($financeSummary['expense']) ?></div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="text-muted small">Outstanding receivables</div><div class="fs-3 fw-bold"><?= money($outstandingReceivables) ?></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card p-3">
            <h6>Active animals by species</h6>
            <table class="table table-sm">
                <?php foreach ($animalsBySpecies as $row): ?>
                    <tr><td><?= e($row['name']) ?></td><td class="text-end"><?= (int) $row['c'] ?></td></tr>
                <?php endforeach; ?>
                <?php if ($animalsBySpecies === []): ?><tr><td class="text-muted">No animals registered yet.</td></tr><?php endif; ?>
            </table>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <h6>Vaccinations due (next 14 days)</h6>
            <table class="table table-sm">
                <?php foreach ($dueVaccinations as $row): ?>
                    <tr><td><?= e($row['animal_code']) ?></td><td><?= e($row['vaccine_name']) ?></td><td><?= fdate($row['next_due_date']) ?></td></tr>
                <?php endforeach; ?>
                <?php if ($dueVaccinations === []): ?><tr><td class="text-muted">None due.</td></tr><?php endif; ?>
            </table>
            <?php if ($overdueVaccinations !== []): ?>
                <div class="alert alert-warning small mb-0"><?= count($overdueVaccinations) ?> vaccination(s) overdue.</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <h6>Low stock items</h6>
            <table class="table table-sm">
                <?php foreach ($lowStock as $row): ?>
                    <tr><td><?= e($row['name']) ?></td><td class="text-end"><?= $row['current_quantity'] ?> <?= e($row['unit']) ?></td></tr>
                <?php endforeach; ?>
                <?php if ($lowStock === []): ?><tr><td class="text-muted">All items above reorder threshold.</td></tr><?php endif; ?>
            </table>
            <div class="small text-muted">Tasks due today: <?= (int) $tasksToday ?></div>
        </div>
    </div>
</div>
