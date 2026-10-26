<?php /** @var array $errors */ use App\Core\Redirect; $base = Redirect::basePath(); ?>
<div class="row justify-content-center"><div class="col-md-5">
<div class="card p-4">
    <h3>Create an account</h3>
    <?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e2): ?><li><?= e($e2) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="<?= e($base) ?>/shop/register">
        <?= csrf_field() ?>
        <input name="name" class="form-control mb-2" placeholder="Full name *" required>
        <input type="email" name="email" class="form-control mb-2" placeholder="Email *" required>
        <input name="phone" class="form-control mb-2" placeholder="Phone">
        <input type="password" name="password" class="form-control mb-2" placeholder="Password (min 8 chars) *" required>
        <button class="btn btn-success w-100">Register</button>
    </form>
</div>
</div></div>
