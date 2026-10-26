<?php
/** @var array $transactions @var array $summary @var array $accounts @var string $from @var string $to
 *  @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<h2 class="mb-3">Accounts &amp; Finance</h2>
<form class="row g-2 mb-3">
    <div class="col-auto"><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
    <div class="col-auto"><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
</form>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card p-3">Income: <strong class="text-success"><?= money($summary['income']) ?></strong></div></div>
    <div class="col-md-4"><div class="card p-3">Expense: <strong class="text-danger"><?= money($summary['expense']) ?></strong></div></div>
    <div class="col-md-4"><div class="card p-3">Net: <strong><?= money($summary['net']) ?></strong></div></div>
</div>

<div class="card p-3 mb-3">
    <h6>Record transaction</h6>
    <form method="post" action="<?= e($base) ?>/finance" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-md-2"><input type="date" name="txn_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div class="col-md-2">
            <select name="type" class="form-select">
                <option value="income">Income</option>
                <option value="expense">Expense</option>
                <option value="transfer">Transfer</option>
            </select>
        </div>
        <div class="col-md-2"><input name="category" class="form-control" placeholder="Category *" required></div>
        <div class="col-md-2"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount *" required></div>
        <div class="col-md-2">
            <select name="account_id" class="form-select">
                <option value="">No account</option>
                <?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?> (<?= money($a['current_balance']) ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-success w-100">Save</button></div>
    </form>
</div>

<div class="card">
<table class="table mb-0">
<thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Notes</th></tr></thead>
<?php foreach ($transactions as $t): ?>
<tr>
    <td><?= fdate($t['txn_date']) ?></td>
    <td><span class="badge bg-<?= $t['type'] === 'income' ? 'success' : ($t['type'] === 'expense' ? 'danger' : 'secondary') ?>"><?= e($t['type']) ?></span></td>
    <td><?= e($t['category']) ?></td>
    <td><?= money($t['amount']) ?></td>
    <td class="small text-muted"><?= e($t['notes'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
<?php if ($transactions === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No transactions in this period.</td></tr><?php endif; ?>
</table>
</div>
