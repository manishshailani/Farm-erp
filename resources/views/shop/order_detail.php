<?php /** @var array $order @var array $items @var array $history @var ?array $invoice */ ?>
<h2>Order <?= e($order['order_number']) ?></h2>
<span class="badge bg-secondary fs-6"><?= e(str_replace('_', ' ', $order['status'])) ?></span>

<div class="row g-3 mt-3">
    <div class="col-md-7">
        <div class="card p-3">
            <h6>Items</h6>
            <table class="table table-sm">
                <?php foreach ($items as $i): ?>
                    <tr><td><?= e($i['product_name_snapshot']) ?></td><td><?= e($i['quantity']) ?></td><td><?= money($i['line_total']) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <p class="text-end">Total: <strong><?= money($order['total_amount']) ?></strong></p>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card p-3 mb-3">
            <h6>Status history</h6>
            <ul class="list-unstyled small mb-0">
                <?php foreach ($history as $h): ?>
                    <li><?= fdate($h['changed_at'], 'd-m-Y H:i') ?> — <?= e(str_replace('_', ' ', $h['status'])) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($invoice !== null): ?>
        <div class="card p-3">
            <h6>Invoice <?= e($invoice['invoice_number']) ?></h6>
            <p>Amount: <?= money($invoice['amount']) ?> &nbsp; Paid: <?= money($invoice['amount_paid']) ?> &nbsp; <span class="badge bg-info text-dark"><?= e($invoice['status']) ?></span></p>
        </div>
        <?php endif; ?>
    </div>
</div>
