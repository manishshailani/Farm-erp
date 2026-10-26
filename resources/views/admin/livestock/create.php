<?php
/** @var array $species @var array $errors @var array $old */
use App\Core\Redirect;
$base = Redirect::basePath();
$v = static fn (string $k, string $d = '') => e($old[$k] ?? $d);
?>
<h2 class="mb-3">Register animal</h2>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e2) : ?><li><?= e($e2) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="card p-4">
<form method="post" action="<?= e($base) ?>/livestock">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Animal code *</label>
            <input name="animal_code" class="form-control" value="<?= $v('animal_code') ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Species *</label>
            <select name="species_id" id="species_id" class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($species as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Breed</label>
            <select name="breed_id" id="breed_id" class="form-select"><option value="">Select species first</option></select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Name / label</label>
            <input name="name" class="form-control" value="<?= $v('name') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Sex *</label>
            <select name="sex" class="form-select">
                <option value="female">Female</option>
                <option value="male">Male</option>
                <option value="unknown">Unknown</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Date of birth</label>
            <input type="date" name="date_of_birth" class="form-control" value="<?= $v('date_of_birth') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Estimated age (months)</label>
            <input type="number" name="estimated_age_months" class="form-control" value="<?= $v('estimated_age_months') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Source *</label>
            <select name="source" class="form-select">
                <option value="born_on_farm">Born on farm</option>
                <option value="purchased">Purchased</option>
                <option value="transferred">Transferred</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Purchase date</label>
            <input type="date" name="purchase_date" class="form-control" value="<?= $v('purchase_date') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Purchase cost (₹)</label>
            <input type="number" step="0.01" name="purchase_cost" class="form-control" value="<?= $v('purchase_cost') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Pen / location</label>
            <input name="pen_location" class="form-control" value="<?= $v('pen_location') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Current weight (kg)</label>
            <input type="number" step="0.01" name="current_weight_kg" class="form-control" value="<?= $v('current_weight_kg') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Dam ID (optional)</label>
            <input type="number" name="dam_id" class="form-control" value="<?= $v('dam_id') ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"><?= $v('notes') ?></textarea>
        </div>
    </div>
    <button class="btn btn-success mt-3" type="submit">Save animal</button>
</form>
</div>
<script>
document.getElementById('species_id').addEventListener('change', function () {
    const speciesId = this.value;
    const breedSelect = document.getElementById('breed_id');
    breedSelect.innerHTML = '<option value="">Loading...</option>';
    if (!speciesId) { breedSelect.innerHTML = '<option value="">Select species first</option>'; return; }
    fetch('<?= e($base) ?>/api/species/' + speciesId + '/breeds')
        .then(r => r.json())
        .then(breeds => {
            breedSelect.innerHTML = '<option value="">None / not specified</option>';
            breeds.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id; opt.textContent = b.name;
                breedSelect.appendChild(opt);
            });
        });
});
</script>
