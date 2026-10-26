<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\Employee;
use App\Models\EmployeeTask;

final class EmployeeController
{
    public function index(Request $request, array $args): void
    {
        View::render('admin/employees/index', [
            'employees' => Employee::all('name ASC', 500),
            'tasks' => EmployeeTask::withEmployee(['status' => $request->string('status')]),
            'success' => Session::pullFlash('success'),
        ]);
    }

    public function store(Request $request, array $args): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $code = $request->string('employee_code') ?: ('EMP-' . str_pad((string) (Employee::count() + 1), 4, '0', STR_PAD_LEFT));
        $pdo->prepare(
            'INSERT INTO employees (employee_code, name, phone, email, job_role, employment_status, joining_date, wage_terms, notes, created_at, updated_at)
             VALUES (:code, :name, :phone, :email, :role, :status, :joining, :wage, :notes, :c, :u)'
        )->execute([
            'code' => $code,
            'name' => $request->string('name'),
            'phone' => $request->string('phone') ?: null,
            'email' => $request->string('email') ?: null,
            'role' => $request->string('job_role') ?: null,
            'status' => 'active',
            'joining' => $request->string('joining_date') ?: null,
            'wage' => $request->string('wage_terms') ?: null,
            'notes' => $request->string('notes') ?: null,
            'c' => $now,
            'u' => $now,
        ]);
        Session::flash('success', 'Employee added.');
        Redirect::to('/employees');
    }

    public function storeTask(Request $request, array $args): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO employee_tasks (title, description, assigned_to, module, priority, due_date, status, created_by, created_at, updated_at)
             VALUES (:title, :desc, :assigned, :module, :priority, :due, "pending", :created_by, :c, :u)'
        )->execute([
            'title' => $request->string('title'),
            'desc' => $request->string('description') ?: null,
            'assigned' => $request->int('assigned_to') ?: null,
            'module' => $request->string('module') ?: null,
            'priority' => $request->string('priority', 'normal'),
            'due' => $request->string('due_date') ?: null,
            'created_by' => Auth::id(),
            'c' => $now,
            'u' => $now,
        ]);
        Session::flash('success', 'Task created.');
        Redirect::to('/employees');
    }

    public function updateTaskStatus(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $status = $request->string('status');
        $pdo = Database::connection();
        $pdo->prepare('UPDATE employee_tasks SET status = :s, completed_at = :done, updated_at = :u WHERE id = :id')
            ->execute([
                's' => $status,
                'done' => $status === 'done' ? date('Y-m-d H:i:s') : null,
                'u' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]);
        Session::flash('success', 'Task updated.');
        Redirect::to('/employees');
    }
}
