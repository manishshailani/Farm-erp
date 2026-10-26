CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    employee_code TEXT NOT NULL,
    name TEXT NOT NULL,
    phone TEXT NULL,
    email TEXT NULL,
    job_role TEXT NULL,
    employment_status TEXT NOT NULL DEFAULT 'active',
    joining_date TEXT NULL,
    wage_terms TEXT NULL,
    notes TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (employee_code),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS employee_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT NULL,
    assigned_to INTEGER NULL,
    module TEXT NULL,
    priority TEXT NOT NULL DEFAULT 'normal',
    due_date TEXT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    completed_at TEXT NULL,
    materials_notes TEXT NULL,
    created_by INTEGER NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL
);
CREATE INDEX idx_task_status ON employee_tasks (status);
CREATE INDEX idx_task_due ON employee_tasks (due_date);
