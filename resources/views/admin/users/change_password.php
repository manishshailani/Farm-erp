<?php
/** @var ?string $success @var ?string $error */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<h2 class="mb-3">My Account — Change Password</h2>

<div class="card p-3" style="max-width: 480px;">
    <form method="post" action="<?= e($base) ?>/account/password">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Current password</label>
            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="mb-3">
            <label class="form-label">New password</label>
            <input type="password" name="new_password" class="form-control" required minlength="10" autocomplete="new-password">
            <div class="form-text">At least 10 characters.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm new password</label>
            <input type="password" name="new_password_confirmation" class="form-control" required minlength="10" autocomplete="new-password">
        </div>
        <button class="btn btn-success">Update password</button>
    </form>
</div>
