<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\Customer;

final class CustomerController
{
    public function index(Request $request, array $args): void
    {
        View::render('admin/customers/index', [
            'customers' => Customer::all('name ASC', 500),
            'success' => Session::pullFlash('success'),
        ]);
    }

    public function store(Request $request, array $args): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO customers (name, phone, email, credit_limit, is_active, created_at, updated_at)
             VALUES (:name, :phone, :email, :limit, 1, :c, :u)'
        )->execute([
            'name' => $request->string('name'),
            'phone' => $request->string('phone') ?: null,
            'email' => $request->string('email') ?: null,
            'limit' => $request->float('credit_limit'),
            'c' => $now,
            'u' => $now,
        ]);
        Session::flash('success', 'Customer added.');
        Redirect::to('/customers');
    }

    public function show(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $customer = Customer::find($id);
        if ($customer === null) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }
        View::render('admin/customers/show', [
            'customer' => $customer,
            'addresses' => Customer::addresses($id),
            'statement' => Customer::statement($id),
            'outstanding' => Customer::outstandingBalance($id),
        ]);
    }
}
