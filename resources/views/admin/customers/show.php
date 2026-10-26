<?php
/** @var array $customer @var array $addresses @var array $statement @var float $outstanding */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<h2><?= e($customer['name']) ?></h2>
<p class="text-muted"><?= e($customer['phone'] ?? '') ?> <?= e($customer['email'] ?? '') ?></p>
<p>Outstanding balance: <strong><?= money($outstanding) ?></strong></p>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card p-3">
            <h6>Addresses</h6>
            <?php foreach ($addresses as $a): ?>
                <p class="small border-bottom pb-2"><?= e($a['label']) ?><br><?= e($a['line1']) ?>, <?= e($a['city']) ?>, <?= e($a['state']) ?> - <?= e($a['pincode']) ?></p>
            <?php endforeach; ?>
            <?php if ($addresses === []): ?><p class="text-muted">No saved addresses.</p><?php endif; ?>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card p-3">
            <h6>Statement (invoices)</h6>
            <table class="table table-sm">
                <thead><tr><th>Invoice</th><th>Order</th><th>Amount</th><th>Paid</th><th>Status</th></tr></thead>
                <?php foreach ($statement as $s): ?>
                    <tr><td><?= e($s['invoice_number']) ?></td><td><?= e($s['order_number']) ?></td><td><?= money($s['amount']) ?></td><td><?= money($s['amount_paid']) ?></td><td><?= e($s['status']) ?></td></tr>
                <?php endforeach; ?>
                <?php if ($statement === []): ?><tr><td colspan="5" class="text-muted">No invoices yet.</td></tr><?php endif; ?>
            </table>
        </div>
    </div>
</div>
