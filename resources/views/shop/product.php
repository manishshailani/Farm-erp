<?php
/** @var array $product */
use App\Core\Auth;
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<div class="row">
    <div class="col-md-8">
        <h2><?= e($product['name']) ?></h2>
        <p><?= e($product['description'] ?? '') ?></p>
        <p class="fs-4"><?= $product['is_quotation_only'] ? 'Quotation on request' : money($product['price']) . ' / ' . e($product['unit']) ?></p>
        <?php if (!$product['is_quotation_only']): ?>
            <p class="text-muted">Available: <?= e($product['available_quantity']) ?> <?= e($product['unit']) ?></p>
        <?php endif; ?>

        <?php if (Auth::check()): ?>
        <form method="post" action="<?= e($base) ?>/shop/cart/add" class="d-flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="number" step="0.01" name="quantity" class="form-control" style="max-width:120px" value="<?= e($product['min_order_quantity']) ?>">
            <button class="btn btn-success">Add to cart</button>
        </form>
        <?php else: ?>
            <a href="<?= e($base) ?>/shop/login" class="btn btn-success">Sign in to order</a>
        <?php endif; ?>
    </div>
</div>
