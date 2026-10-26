CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL, -- linked login account, if the employee also uses the system
    employee_code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    job_role VARCHAR(100) NULL,
    employment_status VARCHAR(20) NOT NULL DEFAULT 'active', -- active, on_leave, terminated
    joining_date DATE NULL,
    wage_terms VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_employee_code (employee_code),
    CONSTRAINT fk_employee_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employee_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    assigned_to INT NULL, -- employees.id
    module VARCHAR(50) NULL, -- livestock, orchard, pond, inventory, general
    priority VARCHAR(10) NOT NULL DEFAULT 'normal', -- low, normal, high
    due_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, in_progress, done, cancelled
    completed_at DATETIME NULL,
    materials_notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_task_employee FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL,
    KEY idx_task_status (status),
    KEY idx_task_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
