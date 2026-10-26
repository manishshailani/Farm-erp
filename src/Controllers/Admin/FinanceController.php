<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\FinancialTransaction;

final class FinanceController
{
    public function index(Request $request, array $args): void
    {
        $from = $request->string('from') ?: date('Y-m-01');
        $to = $request->string('to') ?: date('Y-m-t');

        View::render('admin/finance/index', [
            'transactions' => FinancialTransaction::between($from, $to),
            'summary' => FinancialTransaction::summary($from, $to),
            'accounts' => Database::connection()->query('SELECT * FROM accounts ORDER BY name')->fetchAll(),
            'from' => $from,
            'to' => $to,
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function store(Request $request, array $args): void
    {
        try {
            \App\Services\FinanceService::recordTransaction([
                'txn_date' => $request->string('txn_date', date('Y-m-d')),
                'type' => $request->string('type'),
                'category' => $request->string('category'),
                'enterprise' => $request->string('enterprise') ?: null,
                'amount' => $request->float('amount'),
                'account_id' => $request->int('account_id') ?: null,
                'transfer_to_account_id' => $request->int('transfer_to_account_id') ?: null,
                'payment_method' => $request->string('payment_method') ?: null,
                'counterparty' => $request->string('counterparty') ?: null,
                'reference' => $request->string('reference') ?: null,
                'notes' => $request->string('notes') ?: null,
                'is_capital_expenditure' => $request->bool('is_capital_expenditure'),
            ], Auth::id());
            Session::flash('success', 'Transaction recorded.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/finance');
    }
}
