<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Services\LoginThrottle;

final class AccountController
{
    public function showRegister(Request $request, array $args): void
    {
        View::render('shop/register', ['errors' => Session::pullFlash('errors', [])], 'layouts/shop');
    }

    public function register(Request $request, array $args): void
    {
        $data = $request->all();
        $v = new Validator($data);
        $v->required('name', 'Name')->required('email', 'Email')->email('email', 'Email')->required('password', 'Password');

        $password = $request->string('password');
        if ($v->fails() || strlen($password) < 8) {
            $errors = $v->errors();
            if (strlen($password) < 8) {
                $errors['password'] = 'Password must be at least 8 characters.';
            }
            Session::flash('errors', $errors);
            Redirect::to('/shop/register');
            return;
        }

        $pdo = Database::connection();
        $existing = $pdo->prepare('SELECT id FROM users WHERE email = :e');
        $existing->execute(['e' => $data['email']]);
        if ($existing->fetchColumn() !== false) {
            Session::flash('errors', ['email' => 'An account with this email already exists.']);
            Redirect::to('/shop/register');
            return;
        }

        $roleRow = $pdo->query("SELECT id FROM roles WHERE name = 'Customer'")->fetch();
        if ($roleRow === false) {
            // Self-healing: the "Customer" role is part of the seed data
            // but may be missing on an older seeded database; create it
            // rather than failing registration.
            $pdo->prepare("INSERT INTO roles (name, description, is_system) VALUES ('Customer', 'Public customer account', 1)")->execute();
            $roleRow = ['id' => Database::lastInsertId()];
        }

        $now = date('Y-m-d H:i:s');
        \App\Core\Database::transaction(function ($pdo) use ($data, $roleRow, $now) {
            $pdo->prepare('INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at) VALUES (:n, :e, :p, 1, :c, :u)')
                ->execute([
                    'n' => $data['name'],
                    'e' => $data['email'],
                    'p' => password_hash($data['password'], PASSWORD_BCRYPT),
                    'c' => $now,
                    'u' => $now,
                ]);
            $userId = (int) Database::lastInsertId();
            $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)')->execute(['u' => $userId, 'r' => $roleRow['id']]);
            $pdo->prepare('INSERT INTO customers (user_id, name, phone, email, is_active, created_at, updated_at) VALUES (:u, :n, :p, :e, 1, :c, :up)')
                ->execute([
                    'u' => $userId,
                    'n' => $data['name'],
                    'p' => $data['phone'] ?? null,
                    'e' => $data['email'],
                    'c' => $now,
                    'up' => $now,
                ]);
            Auth::login(['id' => $userId, 'name' => $data['name']]);
        });

        Redirect::to('/shop/account/orders');
    }

    public function showLogin(Request $request, array $args): void
    {
        View::render('shop/login', ['error' => Session::pullFlash('error')], 'layouts/shop');
    }

    public function login(Request $request, array $args): void
    {
        $email = $request->string('email');
        $password = $request->string('password');
        $ip = $request->ip();

        if (LoginThrottle::tooManyAttempts($email, $ip)) {
            Session::flash('error', 'Too many failed login attempts. Please wait a few minutes.');
            Redirect::to('/shop/login');
            return;
        }

        $ok = Auth::attempt($email, $password);
        LoginThrottle::record($email, $ip, $ok);

        if (!$ok) {
            Session::flash('error', 'Invalid email or password.');
            Redirect::to('/shop/login');
            return;
        }
        Redirect::to('/shop/account/orders');
    }

    public function logout(Request $request, array $args): void
    {
        Auth::logout();
        Redirect::to('/shop');
    }

    public function orders(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        View::render('shop/orders', [
            'orders' => Order::withCustomer(['customer_id' => $customer['id']]),
        ], 'layouts/shop');
    }

    public function orderDetail(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        $order = Order::find((int) $args['id']);
        // IDOR guard: a customer may only ever see their OWN order, found
        // via the session's own customer id -- never trust the id in the
        // URL alone.
        if ($order === null || (int) $order['customer_id'] !== (int) $customer['id']) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }
        View::render('shop/order_detail', [
            'order' => $order,
            'items' => Order::items($order['id']),
            'history' => Order::statusHistory($order['id']),
            'invoice' => Order::invoice($order['id']),
        ], 'layouts/shop');
    }

    public function addresses(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        View::render('shop/addresses', [
            'addresses' => Customer::addresses((int) $customer['id']),
            'success' => Session::pullFlash('success'),
        ], 'layouts/shop');
    }

    public function storeAddress(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO customer_addresses (customer_id, label, line1, line2, city, state, pincode, is_default)
             VALUES (:c, :label, :l1, :l2, :city, :state, :pin, :def)'
        )->execute([
            'c' => $customer['id'],
            'label' => $request->string('label', 'Home'),
            'l1' => $request->string('line1'),
            'l2' => $request->string('line2') ?: null,
            'city' => $request->string('city'),
            'state' => $request->string('state'),
            'pin' => $request->string('pincode'),
            'def' => $request->bool('is_default') ? 1 : 0,
        ]);
        Session::flash('success', 'Address saved.');
        Redirect::to('/shop/account/addresses');
    }

    /**
     * Self-service password change for the signed-in customer. Requires
     * the current password to verify ownership of the account (Auth has
     * no email-based reset flow -- see docs/FINAL_ACCEPTANCE_REPORT.md --
     * so this is the only way a customer can change their own password;
     * a customer who has forgotten it entirely has no in-app recovery
     * path and must contact the farm for a staff-side reset via
     * UserController::resetPassword()).
     */
    public function showChangePassword(Request $request, array $args): void
    {
        View::render('shop/change_password', [
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ], 'layouts/shop');
    }

    public function updatePassword(Request $request, array $args): void
    {
        $userId = Auth::id();
        $user = Auth::user();
        if ($userId === null || $user === null) {
            Redirect::to('/shop/login');
            return;
        }

        $current = $request->string('current_password');
        $new = $request->string('new_password');
        $confirm = $request->string('new_password_confirmation');

        if (!Auth::verifyPassword($user, $current)) {
            Session::flash('error', 'Current password is incorrect.');
            Redirect::to('/shop/account/password');
            return;
        }
        if (strlen($new) < 8) {
            Session::flash('error', 'New password must be at least 8 characters.');
            Redirect::to('/shop/account/password');
            return;
        }
        if ($new !== $confirm) {
            Session::flash('error', 'New password confirmation does not match.');
            Redirect::to('/shop/account/password');
            return;
        }

        User::updatePassword($userId, $new);
        Audit::log('user.password_changed_self', 'user', $userId, null, null, 'Self-service password change (customer portal)');
        Session::flash('success', 'Your password has been updated.');
        Redirect::to('/shop/account/password');
    }
}
