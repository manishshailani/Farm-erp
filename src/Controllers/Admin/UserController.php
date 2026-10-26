<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\Role;
use App\Models\User;

final class UserController
{
    public function index(Request $request, array $args): void
    {
        $pdo = Database::connection();
        $users = $pdo->query(
            "SELECT u.*, GROUP_CONCAT(r.name) AS role_names FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             GROUP BY u.id ORDER BY u.name"
        )->fetchAll();

        View::render('admin/users/index', [
            'users' => $users,
            'roles' => Role::allWithPermissionCount(),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function store(Request $request, array $args): void
    {
        $password = $request->string('password');
        if (strlen($password) < 10) {
            Session::flash('error', 'Password must be at least 10 characters.');
            Redirect::to('/users');
            return;
        }
        try {
            $roleIds = array_map('intval', (array) $request->input('role_ids', []));
            User::create($request->string('name'), $request->string('email'), $password, $roleIds);
            Session::flash('success', 'User created.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not create user: ' . $e->getMessage());
        }
        Redirect::to('/users');
    }

    public function toggleActive(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $user = User::find($id);
        if ($user !== null) {
            User::setActive($id, !((bool) $user['is_active']));
        }
        Redirect::to('/users');
    }

    public function updateRoles(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $roleIds = array_map('intval', (array) $request->input('role_ids', []));
        User::syncRoles($id, $roleIds);
        Session::flash('success', 'Roles updated.');
        Redirect::to('/users');
    }

    /**
     * Admin-initiated password reset for ANOTHER user's account.
     *
     * Gated by the same 'users.manage' permission as the rest of this
     * controller (enforced at the route level in src/routes.php). No
     * current-password confirmation is required here, by design --
     * this is the recovery path for a user who is locked out and cannot
     * supply their own current password, which is exactly the gap
     * identified in docs/FINAL_ACCEPTANCE_REPORT.md. The new password is
     * never logged; only the fact that a reset occurred and who performed
     * it is written to the audit trail.
     */
    public function resetPassword(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $target = User::find($id);
        if ($target === null) {
            Session::flash('error', 'User not found.');
            Redirect::to('/users');
            return;
        }

        $password = $request->string('password');
        $confirm = $request->string('password_confirmation');
        if (strlen($password) < 10) {
            Session::flash('error', 'Password must be at least 10 characters.');
            Redirect::to('/users');
            return;
        }
        if ($password !== $confirm) {
            Session::flash('error', 'Password confirmation does not match.');
            Redirect::to('/users');
            return;
        }

        User::updatePassword($id, $password);
        Audit::log(
            'user.password_reset_by_admin',
            'user',
            $id,
            null,
            null,
            'Password reset by administrator user_id=' . (string) Auth::id()
        );
        Session::flash('success', 'Password reset for ' . $target['name'] . '.');
        Redirect::to('/users');
    }

    /**
     * Self-service password change for the CURRENTLY signed-in staff
     * user. Requires the current password to be supplied and verified
     * (Auth::verifyPassword()) before accepting the new one, since this
     * route has no 'users.manage' gate -- any authenticated staff account
     * may change its own password this way, but only its own (the target
     * id is always Auth::id(), never taken from the request).
     */
    public function showChangePassword(Request $request, array $args): void
    {
        View::render('admin/users/change_password', [
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function updateOwnPassword(Request $request, array $args): void
    {
        $userId = Auth::id();
        $user = Auth::user();
        if ($userId === null || $user === null) {
            Redirect::to('/login');
            return;
        }

        $current = $request->string('current_password');
        $new = $request->string('new_password');
        $confirm = $request->string('new_password_confirmation');

        if (!Auth::verifyPassword($user, $current)) {
            Session::flash('error', 'Current password is incorrect.');
            Redirect::to('/account/password');
            return;
        }
        if (strlen($new) < 10) {
            Session::flash('error', 'New password must be at least 10 characters.');
            Redirect::to('/account/password');
            return;
        }
        if ($new !== $confirm) {
            Session::flash('error', 'New password confirmation does not match.');
            Redirect::to('/account/password');
            return;
        }

        User::updatePassword($userId, $new);
        Audit::log('user.password_changed_self', 'user', $userId, null, null, 'Self-service password change');
        Session::flash('success', 'Your password has been updated.');
        Redirect::to('/account/password');
    }
}
