<?php
/** @var array $users @var array $roles @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<h2 class="mb-3">Users &amp; Permissions</h2>

<div class="card p-3 mb-3">
    <h6>Create user</h6>
    <form method="post" action="<?= e($base) ?>/users" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-md-3"><input name="name" class="form-control" placeholder="Name *" required></div>
        <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email *" required></div>
        <div class="col-md-2"><input type="password" name="password" class="form-control" placeholder="Password (min 10 chars) *" required></div>
        <div class="col-md-3">
            <select name="role_ids[]" class="form-select" multiple>
                <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1"><button class="btn btn-success w-100">Add</button></div>
    </form>
</div>

<div class="card">
<table class="table mb-0">
<thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Status</th><th></th></tr></thead>
<?php foreach ($users as $u): ?>
<tr>
    <td><?= e($u['name']) ?></td>
    <td><?= e($u['email']) ?></td>
    <td><?= e($u['role_names'] ?? '-') ?></td>
    <td><span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Disabled' ?></span></td>
    <td class="d-flex gap-2">
        <form method="post" action="<?= e($base) ?>/users/<?= (int) $u['id'] ?>/toggle-active" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-secondary"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#pwd-<?= (int) $u['id'] ?>">Reset password</button>
    </td>
</tr>
<tr class="collapse" id="pwd-<?= (int) $u['id'] ?>">
    <td colspan="5">
        <form method="post" action="<?= e($base) ?>/users/<?= (int) $u['id'] ?>/password" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <input type="password" name="password" class="form-control" placeholder="New password (min 10 chars)" required minlength="10" autocomplete="new-password">
            </div>
            <div class="col-md-4">
                <input type="password" name="password_confirmation" class="form-control" placeholder="Confirm new password" required minlength="10" autocomplete="new-password">
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100">Set password</button>
            </div>
        </form>
        <div class="form-text">This resets <?= e($u['name']) ?>'s password without needing their current one -- use for a locked-out staff account.</div>
    </td>
</tr>
<?php endforeach; ?>
</table>
</div>

<h5 class="mt-4">Roles</h5>
<div class="card">
<table class="table mb-0">
<thead><tr><th>Role</th><th>Permissions</th></tr></thead>
<?php foreach ($roles as $r): ?>
<tr><td><?= e($r['name']) ?></td><td><?= (int) $r['permission_count'] ?> permissions</td></tr>
<?php endforeach; ?>
</table>
</div>
