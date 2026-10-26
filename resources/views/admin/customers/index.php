<?php
/** @var array $customers @var ?string $success */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<div class="d-flex justify-content-between mb-3">
    <h2>Customers</h2>
    <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#newCustomer">+ New customer</button>
</div>
<div class="collapse mb-3" id="newCustomer">
    <div class="card p-3">
        <form method="post" action="<?= e($base) ?>/customers" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-md-4"><input name="name" class="form-control" placeholder="Name *" required></div>
            <div class="col-md-3"><input name="phone" class="form-control" placeholder="Phone"></div>
            <div class="col-md-3"><input name="email" class="form-control" placeholder="Email"></div>
            <div class="col-md-2"><button class="btn btn-success w-100">Add</button></div>
        </form>
    </div>
</div>
<div class="card">
<table class="table mb-0">
<thead><tr><th>Name</th><th>Phone</th><th>Email</th><th></th></tr></thead>
<?php foreach ($customers as $c): ?>
<tr><td><?= e($c['name']) ?></td><td><?= e($c['phone'] ?? '-') ?></td><td><?= e($c['email'] ?? '-') ?></td>
<td><a href="<?= e($base) ?>/customers/<?= (int) $c['id'] ?>">View</a></td></tr>
<?php endforeach; ?>
<?php if ($customers === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No customers yet.</td></tr><?php endif; ?>
</table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
