<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Models\FinancialTransaction;
use App\Services\LivestockService;

final class ReportController
{
    public function index(Request $request, array $args): void
    {
        View::render('admin/reports/index', []);
    }

    public function livestockRegister(Request $request, array $args): void
    {
        $rows = Database::connection()->query(
            "SELECT a.animal_code, s.name AS species, b.name AS breed, a.sex, a.date_of_birth, a.status, a.current_weight_kg
             FROM animals a JOIN species s ON s.id = a.species_id LEFT JOIN breeds b ON b.id = a.breed_id
             ORDER BY a.animal_code"
        )->fetchAll();

        $this->maybeCsv($request, $rows, 'livestock_register.csv', 'admin/reports/livestock_register', ['rows' => $rows]);
    }

    public function vaccinationDue(Request $request, array $args): void
    {
        $from = $request->string('from') ?: date('Y-m-d');
        $to = $request->string('to') ?: date('Y-m-d', strtotime('+30 days'));
        $rows = LivestockService::dueVaccinationsBetween($from, $to);
        $this->maybeCsv($request, $rows, 'vaccination_due.csv', 'admin/reports/vaccination_due', ['rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    public function inventoryStock(Request $request, array $args): void
    {
        $rows = Database::connection()->query('SELECT sku, name, unit, current_quantity, reorder_threshold, average_cost FROM inventory_items ORDER BY name')->fetchAll();
        $this->maybeCsv($request, $rows, 'inventory_stock.csv', 'admin/reports/inventory_stock', ['rows' => $rows]);
    }

    public function salesRegister(Request $request, array $args): void
    {
        $from = $request->string('from') ?: date('Y-m-01');
        $to = $request->string('to') ?: date('Y-m-t');
        $stmt = Database::connection()->prepare(
            "SELECT o.order_number, c.name AS customer_name, o.status, o.total_amount, o.placed_at
             FROM orders o JOIN customers c ON c.id = o.customer_id
             WHERE date(o.placed_at) BETWEEN :f AND :t ORDER BY o.placed_at"
        );
        $stmt->execute(['f' => $from, 't' => $to]);
        $rows = $stmt->fetchAll();
        $this->maybeCsv($request, $rows, 'sales_register.csv', 'admin/reports/sales_register', ['rows' => $rows, 'from' => $from, 'to' => $to]);
    }

    public function incomeExpense(Request $request, array $args): void
    {
        $from = $request->string('from') ?: date('Y-m-01');
        $to = $request->string('to') ?: date('Y-m-t');
        $rows = FinancialTransaction::between($from, $to);
        $summary = FinancialTransaction::summary($from, $to);
        $this->maybeCsv($request, $rows, 'income_expense.csv', 'admin/reports/income_expense', ['rows' => $rows, 'summary' => $summary, 'from' => $from, 'to' => $to]);
    }

    public function receivables(Request $request, array $args): void
    {
        $rows = Database::connection()->query(
            "SELECT c.name AS customer_name, i.invoice_number, i.amount, i.amount_paid, (i.amount - i.amount_paid) AS balance, i.status, i.issued_at
             FROM invoices i JOIN customers c ON c.id = i.customer_id
             WHERE i.status != 'void' AND (i.amount - i.amount_paid) > 0 ORDER BY i.issued_at"
        )->fetchAll();
        $this->maybeCsv($request, $rows, 'receivables.csv', 'admin/reports/receivables', ['rows' => $rows]);
    }

    private function maybeCsv(Request $request, array $rows, string $filename, string $view, array $data): void
    {
        if ($request->string('format') === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            if ($rows !== []) {
                fputcsv($out, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
            }
            fclose($out);
            return;
        }
        View::render($view, $data);
    }
}
