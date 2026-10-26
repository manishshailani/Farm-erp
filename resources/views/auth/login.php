<?php /** @var string|null $error */ use App\Core\Redirect; $base = Redirect::basePath(); ?>
<div class="card p-4">
    <h3 class="text-center mb-3">🌾 Farm ERP</h3>
    <p class="text-center text-muted">Staff sign in</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e($base) ?>/login">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-success w-100" type="submit">Sign in</button>
    </form>
</div>
