<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;

final class OrderController
{
    public function index(Request $request, array $args): void
    {
        View::render('admin/orders/index', [
            'orders' => Order::withCustomer(['status' => $request->string('status')]),
            'statusFilter' => $request->string('status'),
        ]);
    }

    public function show(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $order = Order::find($id);
        if ($order === null) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }
        View::render('admin/orders/show', [
            'order' => $order,
            'items' => Order::items($id),
            'history' => Order::statusHistory($id),
            'invoice' => Order::invoice($id),
            'validTransitions' => OrderService::VALID_TRANSITIONS[$order['status']] ?? [],
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function transition(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        try {
            OrderService::transition($id, $request->string('status'), Auth::id(), $request->string('notes') ?: null);
            Session::flash('success', 'Order status updated.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/orders/' . $id);
    }

    public function recordPayment(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $invoiceId = $request->int('invoice_id');
        try {
            PaymentService::record(
                $invoiceId,
                $request->float('amount'),
                $request->string('method', 'cash'),
                $request->string('reference') ?: null,
                $request->string('notes') ?: null,
                $request->bool('is_refund'),
                Auth::id(),
            );
            Session::flash('success', 'Payment recorded.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/orders/' . $id);
    }
}
