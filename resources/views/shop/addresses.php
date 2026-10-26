<?php /** @var array $addresses @var ?string $success */ use App\Core\Redirect; $base = Redirect::basePath(); ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<h2 class="mb-3">My addresses</h2>
<div class="row g-3">
    <div class="col-md-5">
        <div class="card p-3">
            <h6>Add address</h6>
            <form method="post" action="<?= e($base) ?>/shop/account/addresses">
                <?= csrf_field() ?>
                <input name="label" class="form-control mb-2" placeholder="Label (e.g. Home)" value="Home">
                <input name="line1" class="form-control mb-2" placeholder="Address line 1 *" required>
                <input name="line2" class="form-control mb-2" placeholder="Address line 2">
                <input name="city" class="form-control mb-2" placeholder="City *" required>
                <input name="state" class="form-control mb-2" placeholder="State *" required>
                <input name="pincode" class="form-control mb-2" placeholder="Pincode *" required>
                <button class="btn btn-success w-100">Save address</button>
            </form>
        </div>
    </div>
    <div class="col-md-7">
        <?php foreach ($addresses as $a): ?>
            <div class="card p-3 mb-2"><?= e($a['label']) ?><br><?= e($a['line1']) ?>, <?= e($a['city']) ?>, <?= e($a['state']) ?> - <?= e($a['pincode']) ?></div>
        <?php endforeach; ?>
        <?php if ($addresses === []): ?><p class="text-muted">No saved addresses yet.</p><?php endif; ?>
    </div>
</div>
