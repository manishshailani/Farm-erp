<?php use App\Core\Redirect; $base = Redirect::basePath(); ?>
<h2 class="mb-3">Reports</h2>
<div class="list-group">
    <a class="list-group-item" href="<?= e($base) ?>/reports/livestock-register">Livestock register</a>
    <a class="list-group-item" href="<?= e($base) ?>/reports/vaccination-due">Vaccination due/overdue</a>
    <a class="list-group-item" href="<?= e($base) ?>/reports/inventory-stock">Inventory &amp; stock levels</a>
    <a class="list-group-item" href="<?= e($base) ?>/reports/sales-register">Sales &amp; order register</a>
    <a class="list-group-item" href="<?= e($base) ?>/reports/income-expense">Income &amp; expense report</a>
    <a class="list-group-item" href="<?= e($base) ?>/reports/receivables">Customer receivables</a>
</div>
