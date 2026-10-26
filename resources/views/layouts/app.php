<?php
/** @var string $content */
use App\Core\Auth;
use App\Core\Permission;

$user = Auth::user();
$nav = [
    ['path' => '/dashboard', 'label' => 'Dashboard', 'perm' => null],
    ['path' => '/livestock', 'label' => 'Livestock', 'perm' => 'livestock.view'],
    ['path' => '/livestock-rfid/scan', 'label' => 'RFID Scanning', 'perm' => 'livestock.rfid.scan'],
    ['path' => '/inventory', 'label' => 'Inventory', 'perm' => 'inventory.view'],
    ['path' => '/orchard', 'label' => 'Orchard', 'perm' => 'orchard.view'],
    ['path' => '/pond', 'label' => 'Ponds', 'perm' => 'pond.view'],
    ['path' => '/employees', 'label' => 'Employees &amp; Tasks', 'perm' => 'employees.view'],
    ['path' => '/customers', 'label' => 'Customers', 'perm' => 'customers.view'],
    ['path' => '/orders', 'label' => 'Sales &amp; Orders', 'perm' => 'orders.view'],
    ['path' => '/finance', 'label' => 'Accounts &amp; Finance', 'perm' => 'finance.view'],
    ['path' => '/reports', 'label' => 'Reports', 'perm' => 'reports.view'],
    ['path' => '/audit', 'label' => 'Audit Log', 'perm' => 'audit.view'],
    ['path' => '/users', 'label' => 'Users &amp; Permissions', 'perm' => 'users.manage'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Farm ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f5f7f5; }
        .sidebar { min-height: 100vh; background:#1b4332; color:#fff; }
        .sidebar a { color:#d8f3dc; text-decoration:none; display:block; padding:.5rem 1rem; border-radius:.375rem; }
        .sidebar a:hover, .sidebar a.active { background:#2d6a4f; color:#fff; }
        .card { border:none; box-shadow:0 1px 3px rgba(0,0,0,.08); }
    </style>
</head>
<body>
<div class="d-flex">
    <nav class="sidebar p-3" style="width:240px;">
        <h5 class="mb-4">🌾 Farm ERP</h5>
        <?php foreach ($nav as $item): if ($item['perm'] === null || Permission::currentUserHas($item['perm'])): ?>
            <a href="<?= e(\App\Core\Redirect::basePath() . $item['path']) ?>"><?= $item['label'] /* label is a trusted static string above */ ?></a>
        <?php endif; endforeach; ?>
        <hr class="text-white-50">
        <a href="<?= e(\App\Core\Redirect::basePath() . '/account/password') ?>">My Account</a>
        <form method="post" action="<?= e(\App\Core\Redirect::basePath() . '/logout') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-light w-100" type="submit">Sign out (<?= e($user['name'] ?? '') ?>)</button>
        </form>
    </nav>
    <main class="flex-grow-1 p-4">
        <?= $content ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
