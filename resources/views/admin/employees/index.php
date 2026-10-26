<?php
/** @var array $employees @var array $tasks @var ?string $success */
use App\Core\Redirect;
$base = Redirect::basePath();
?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<h2 class="mb-3">Employees &amp; Tasks</h2>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card p-3 mb-3">
            <h6>Add employee</h6>
            <form method="post" action="<?= e($base) ?>/employees">
                <?= csrf_field() ?>
                <input name="name" class="form-control mb-2" placeholder="Name *" required>
                <input name="phone" class="form-control mb-2" placeholder="Phone">
                <input name="job_role" class="form-control mb-2" placeholder="Job role">
                <input type="date" name="joining_date" class="form-control mb-2">
                <button class="btn btn-success">Add</button>
            </form>
        </div>
        <div class="card p-3">
            <h6>Employees</h6>
            <table class="table table-sm">
                <?php foreach ($employees as $emp): ?>
                    <tr><td><?= e($emp['employee_code']) ?></td><td><?= e($emp['name']) ?></td><td><?= e($emp['job_role'] ?? '-') ?></td></tr>
                <?php endforeach; ?>
                <?php if ($employees === []): ?><tr><td class="text-muted">No employees yet.</td></tr><?php endif; ?>
            </table>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card p-3 mb-3">
            <h6>Assign task</h6>
            <form method="post" action="<?= e($base) ?>/employees/tasks" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-md-5"><input name="title" class="form-control" placeholder="Task title *" required></div>
                <div class="col-md-4">
                    <select name="assigned_to" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($employees as $emp): ?><option value="<?= (int) $emp['id'] ?>"><?= e($emp['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><input type="date" name="due_date" class="form-control"></div>
                <div class="col-md-8"><input name="description" class="form-control" placeholder="Description"></div>
                <div class="col-md-4"><button class="btn btn-success w-100">Create task</button></div>
            </form>
        </div>
        <div class="card p-3">
            <h6>Tasks</h6>
            <table class="table table-sm">
                <thead><tr><th>Title</th><th>Assigned to</th><th>Due</th><th>Status</th><th></th></tr></thead>
                <?php foreach ($tasks as $t): ?>
                <tr>
                    <td><?= e($t['title']) ?></td>
                    <td><?= e($t['employee_name'] ?? '-') ?></td>
                    <td><?= fdate($t['due_date']) ?></td>
                    <td><span class="badge bg-secondary"><?= e($t['status']) ?></span></td>
                    <td>
                        <?php if ($t['status'] !== 'done'): ?>
                        <form method="post" action="<?= e($base) ?>/employees/tasks/<?= (int) $t['id'] ?>/status" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="done">
                            <button class="btn btn-sm btn-outline-success">Mark done</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($tasks === []): ?><tr><td colspan="5" class="text-muted">No tasks yet.</td></tr><?php endif; ?>
            </table>
        </div>
    </div>
</div>
