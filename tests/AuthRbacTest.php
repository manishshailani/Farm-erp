<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Permission;
use App\Models\User;

return function (): void {
    echo "AuthRbacTest\n";

    T::run('Workflow #1: create administrator and sign in', function () {
        $pdo = test_pdo();
        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'Super Administrator'")->fetchColumn();
        T::assertTrue($roleId > 0, 'Super Administrator role must exist from seed data');

        $userId = User::create('Test Admin', 'test-admin@example.com', 'StrongPassword123', [$roleId]);
        T::assertTrue($userId > 0, 'admin user should be created with an id');

        $ok = Auth::attempt('test-admin@example.com', 'StrongPassword123');
        T::assertTrue($ok, 'correct credentials must authenticate');

        $wrong = Auth::attempt('test-admin@example.com', 'WrongPassword');
        T::assertTrue(!$wrong, 'wrong password must be rejected');
    });

    T::run('Password is hashed, never stored in plain text', function () {
        $pdo = test_pdo();
        $row = $pdo->prepare('SELECT password_hash FROM users WHERE email = :e');
        $row->execute(['e' => 'test-admin@example.com']);
        $hash = $row->fetch()['password_hash'];
        T::assertTrue($hash !== 'StrongPassword123', 'password must not be stored verbatim');
        T::assertTrue(password_verify('StrongPassword123', $hash), 'stored hash must verify against the real password');
    });

    T::run('Workflow #2: create users and assign roles; permissions follow the role', function () {
        $pdo = test_pdo();
        $accountantRoleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'Accountant'")->fetchColumn();
        $userId = User::create('Test Accountant', 'accountant@example.com', 'AccountantPass123', [$accountantRoleId]);

        T::assertTrue(Permission::userHas($userId, 'finance.manage'), 'Accountant role must grant finance.manage');
        T::assertTrue(!Permission::userHas($userId, 'livestock.manage'), 'Accountant role must NOT grant livestock.manage');
    });

    T::run('Workflow #22: field worker cannot access restricted financial actions', function () {
        $pdo = test_pdo();
        $workerRoleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'Employee / Field Worker'")->fetchColumn();
        $userId = User::create('Test Worker', 'worker@example.com', 'WorkerPass123', [$workerRoleId]);

        T::assertTrue(!Permission::userHas($userId, 'finance.manage'), 'field worker must not have finance.manage');
        T::assertTrue(!Permission::userHas($userId, 'orders.refund'), 'field worker must not have orders.refund');
        T::assertTrue(Permission::userHas($userId, 'livestock.rfid.scan'), 'field worker should still be able to scan RFID tags');
    });

    T::run('Disabled user cannot authenticate even with correct password', function () {
        User::setActive(
            (int) test_pdo()->query("SELECT id FROM users WHERE email = 'worker@example.com'")->fetchColumn(),
            false
        );
        $ok = Auth::attempt('worker@example.com', 'WorkerPass123');
        T::assertTrue(!$ok, 'a disabled account must not be able to sign in');
    });

    T::run('Self-service password change: old password stops working, new password works', function () {
        $pdo = test_pdo();
        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'Accountant'")->fetchColumn();
        $userId = User::create('Password Change Test', 'pwchange@example.com', 'OriginalPass123', [$roleId]);

        $user = User::find($userId);
        T::assertTrue(Auth::verifyPassword($user, 'OriginalPass123'), 'current-password check must accept the real password');
        T::assertTrue(!Auth::verifyPassword($user, 'WrongGuess123'), 'current-password check must reject a wrong password');

        User::updatePassword($userId, 'BrandNewPass456');

        $oldStillWorks = Auth::attempt('pwchange@example.com', 'OriginalPass123');
        T::assertTrue(!$oldStillWorks, 'after changing the password, the OLD password must no longer authenticate');

        $newWorks = Auth::attempt('pwchange@example.com', 'BrandNewPass456');
        T::assertTrue($newWorks, 'after changing the password, the NEW password must authenticate');
    });

    T::run('Admin password reset does not require the target user\'s current password', function () {
        $pdo = test_pdo();
        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'Employee / Field Worker'")->fetchColumn();
        $userId = User::create('Locked Out User', 'lockedout@example.com', 'ForgottenPass123', [$roleId]);

        // Simulate an administrator resetting a locked-out user's password
        // without knowing the old one -- this is the recovery path added
        // to close the gap documented in docs/FINAL_ACCEPTANCE_REPORT.md.
        User::updatePassword($userId, 'AdminSetThisPass789');

        T::assertTrue(!Auth::attempt('lockedout@example.com', 'ForgottenPass123'), 'the forgotten password must no longer work');
        T::assertTrue(Auth::attempt('lockedout@example.com', 'AdminSetThisPass789'), 'the admin-set password must now work');
    });
};
