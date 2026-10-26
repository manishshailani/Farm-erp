<?php
/** @var array{tag:string,animal:?array}|null $result */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<h2 class="mb-3">RFID Scanning</h2>
<div class="card p-4 mb-3">
    <p class="text-muted">Click into the box below, then scan the tag with a USB keyboard-wedge reader (it types the number and presses Enter automatically), or type the number manually and press Enter.</p>
    <form method="post" action="<?= e($base) ?>/livestock-rfid/scan">
        <?= csrf_field() ?>
        <input name="tag_number" class="form-control form-control-lg" placeholder="Scan or type tag number..." autofocus autocomplete="off">
    </form>
</div>

<?php if ($result !== null): ?>
    <div class="card p-4">
        <?php if ($result['animal'] !== null): $a = $result['animal']; ?>
            <div class="alert alert-success">Tag <strong><?= e($result['tag']) ?></strong> matched animal <strong><?= e($a['animal_code']) ?></strong>.</div>
            <a class="btn btn-success" href="<?= e($base) ?>/livestock/<?= (int) $a['id'] ?>">Open animal record &rarr;</a>
        <?php else: ?>
            <div class="alert alert-warning">Tag <strong><?= e($result['tag']) ?></strong> is not registered to any active animal. Register the animal first, then assign this tag from its record page.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>
