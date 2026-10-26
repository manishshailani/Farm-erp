<?php /** @var ?string $error */ use App\Core\Redirect; $base = Redirect::basePath(); ?>
<div class="row justify-content-center"><div class="col-md-5">
<div class="card p-4">
    <h3>Sign in</h3>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e($base) ?>/shop/login">
        <?= csrf_field() ?>
        <input type="email" name="email" class="form-control mb-2" placeholder="Email" required autofocus>
        <input type="password" name="password" class="form-control mb-2" placeholder="Password" required>
        <button class="btn btn-success w-100">Sign in</button>
    </form>
    <p class="mt-2 small">New here? <a href="<?= e($base) ?>/shop/register">Create an account</a></p>
</div>
</div></div>
