<?php

declare(strict_types=1);

/**
 * Seeds roles and permissions (section 4 of the spec) and assigns
 * permission sets to each role. Idempotent: uses INSERT ... IGNORE /
 * INSERT OR IGNORE equivalents so re-running the seed script never
 * duplicates rows or fails on a second run.
 *
 * Run via: php bin/console.php seed
 */

use App\Core\Database;

return static function (): void {
    $pdo = Database::connection();
    $driver = Database::driver();
    $ignore = $driver === 'sqlite' ? 'OR IGNORE' : 'IGNORE';

    $permissions = [
        // Livestock
        'livestock.view' => 'View livestock records',
        'livestock.manage' => 'Create/edit/dispose livestock records',
        'livestock.rfid.manage' => 'Assign/deactivate RFID tags',
        'livestock.rfid.scan' => 'Use RFID scanning / check-in workflow',
        'livestock.vaccination.manage' => 'Record vaccinations and treatments',
        'livestock.sell' => 'Sell / reserve an animal',
        // Orchard & pond
        'orchard.view' => 'View orchard blocks, trees and harvests',
        'orchard.manage' => 'Manage orchard blocks, trees and harvests',
        'pond.view' => 'View ponds, stocking batches and water quality readings',
        'pond.manage' => 'Manage ponds, stocking and water quality',
        // Inventory
        'inventory.view' => 'View inventory and stock levels',
        'inventory.manage' => 'Receive stock, adjust quantities, manage items',
        // Employees
        'employees.view' => 'View employee records',
        'employees.manage' => 'Create/edit employee records',
        'employees.salary.view' => 'View salary/wage information',
        'tasks.manage' => 'Create and assign tasks',
        // Finance
        'finance.view' => 'View financial transactions and reports',
        'finance.manage' => 'Record income/expense transactions',
        'finance.approve' => 'Approve financial transactions',
        // Sales / customers / orders
        'customers.view' => 'View customer accounts',
        'customers.manage' => 'Create/edit customer accounts',
        'orders.view' => 'View orders',
        'orders.manage' => 'Confirm/update/fulfil orders',
        'orders.refund' => 'Issue refunds and cancellations',
        'payments.record' => 'Record payments received',
        // Catalogue
        'catalogue.manage' => 'Manage public product catalogue',
        // Admin
        'users.manage' => 'Create users and assign roles',
        'roles.manage' => 'Create roles and assign permissions',
        'reports.view' => 'View and export reports',
        'settings.manage' => 'Change system settings',
        'audit.view' => 'View audit logs',
    ];

    foreach ($permissions as $key => $description) {
        $pdo->prepare("INSERT {$ignore} INTO permissions (`key`, description) VALUES (:k, :d)")
            ->execute(['k' => $key, 'd' => $description]);
    }

    $roles = [
        'Super Administrator' => array_keys($permissions), // everything
        'Farm Owner' => array_keys($permissions),
        'Farm Manager' => [
            'livestock.view', 'livestock.manage', 'livestock.rfid.manage', 'livestock.rfid.scan',
            'livestock.vaccination.manage', 'livestock.sell',
            'orchard.view', 'orchard.manage', 'pond.view', 'pond.manage',
            'inventory.view', 'inventory.manage',
            'employees.view', 'employees.manage', 'tasks.manage',
            'finance.view', 'customers.view', 'customers.manage',
            'orders.view', 'orders.manage', 'catalogue.manage', 'reports.view',
        ],
        'Livestock and Veterinary Records Manager' => [
            'livestock.view', 'livestock.manage', 'livestock.rfid.manage', 'livestock.rfid.scan',
            'livestock.vaccination.manage', 'inventory.view', 'reports.view',
        ],
        'Orchard and Crop Manager' => ['orchard.view', 'orchard.manage', 'inventory.view', 'inventory.manage', 'reports.view'],
        'Pond Manager' => ['pond.view', 'pond.manage', 'inventory.view', 'inventory.manage', 'reports.view'],
        'Inventory Manager' => ['inventory.view', 'inventory.manage', 'reports.view'],
        'Accountant' => ['finance.view', 'finance.manage', 'finance.approve', 'orders.view', 'customers.view', 'payments.record', 'reports.view'],
        'Sales and Order Manager' => ['orders.view', 'orders.manage', 'orders.refund', 'customers.view', 'customers.manage', 'catalogue.manage', 'payments.record', 'reports.view'],
        'Employee / Field Worker' => ['livestock.view', 'livestock.rfid.scan', 'livestock.vaccination.manage', 'inventory.view', 'tasks.manage'],
        // Customers authenticate through the same users table (see
        // ShopAuth::class) but hold no backend permissions at all -- the
        // public site's own controllers, not RBAC, scope a customer to
        // their own data.
        'Customer' => [],
    ];

    foreach ($roles as $roleName => $permKeys) {
        $pdo->prepare("INSERT {$ignore} INTO roles (name, description, is_system) VALUES (:n, :d, 1)")
            ->execute(['n' => $roleName, 'd' => $roleName . ' (seeded system role)']);

        $roleId = (int) $pdo->query('SELECT id FROM roles WHERE name = ' . $pdo->quote($roleName))->fetchColumn();

        foreach ($permKeys as $permKey) {
            $permId = (int) $pdo->query('SELECT id FROM permissions WHERE `key` = ' . $pdo->quote($permKey))->fetchColumn();
            if ($permId === 0) {
                continue;
            }
            $pdo->prepare("INSERT {$ignore} INTO role_permissions (role_id, permission_id) VALUES (:r, :p)")
                ->execute(['r' => $roleId, 'p' => $permId]);
        }
    }
};
