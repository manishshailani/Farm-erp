<?php
/** @var string $content */
use App\Core\Auth;
use App\Core\Redirect;
$base = Redirect::basePath();
$loggedIn = Auth::check();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Integrated Farm - Online Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#fdfaf3;} .navbar{background:#1b4332;}</style>
</head>
<body>
<nav class="navbar navbar-dark navbar-expand-lg mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?= e($base) ?>/shop">🌾 Integrated Farm</a>
        <div class="d-flex gap-2">
            <?php if ($loggedIn): ?>
                <a class="btn btn-sm btn-outline-light" href="<?= e($base) ?>/shop/cart">Cart</a>
                <a class="btn btn-sm btn-outline-light" href="<?= e($base) ?>/shop/account/orders">My Orders</a>
                <a class="btn btn-sm btn-outline-light" href="<?= e($base) ?>/shop/account/password">My Account</a>
                <form method="post" action="<?= e($base) ?>/shop/logout" class="d-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-light" type="submit">Sign out</button>
                </form>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-light" href="<?= e($base) ?>/shop/login">Sign in</a>
                <a class="btn btn-sm btn-light" href="<?= e($base) ?>/shop/register">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container pb-5"><?= $content ?></div>
</body>
</html>
