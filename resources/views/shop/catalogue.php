<?php
/** @var array $products @var array $categories @var ?string $selectedCategory */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<h2 class="mb-3">Shop</h2>
<div class="mb-3">
    <a class="btn btn-sm <?= $selectedCategory === null ? 'btn-success' : 'btn-outline-success' ?>" href="<?= e($base) ?>/shop">All</a>
    <?php foreach ($categories as $c): ?>
        <a class="btn btn-sm <?= $selectedCategory === $c ? 'btn-success' : 'btn-outline-success' ?>" href="<?= e($base) ?>/shop?category=<?= urlencode($c) ?>"><?= e($c) ?></a>
    <?php endforeach; ?>
</div>
<div class="row g-3">
<?php foreach ($products as $p): ?>
    <div class="col-md-4">
        <div class="card p-3 h-100">
            <h5><?= e($p['name']) ?></h5>
            <p class="text-muted small"><?= e($p['description'] ?? '') ?></p>
            <p><?= $p['is_quotation_only'] ? '<span class="badge bg-warning text-dark">Quotation on request</span>' : money($p['price']) . ' / ' . e($p['unit']) ?></p>
            <a href="<?= e($base) ?>/shop/products/<?= (int) $p['id'] ?>" class="btn btn-outline-success mt-auto">View</a>
        </div>
    </div>
<?php endforeach; ?>
<?php if ($products === []): ?><p class="text-muted">No products available right now.</p><?php endif; ?>
</div>
