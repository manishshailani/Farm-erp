<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\View;
use App\Services\LivestockService;

final class DashboardController
{
    public function index(Request $request, array $args): void
    {
        $pdo = Database::connection();

        $animalsBySpecies = $pdo->query(
            "SELECT s.name, COUNT(*) AS c FROM animals a JOIN species s ON s.id = a.species_id
             WHERE a.status = 'active' GROUP BY s.name ORDER BY c DESC"
        )->fetchAll();

        $today = date('Y-m-d');
        $soon = date('Y-m-d', strtotime('+14 days'));
        $dueVaccinations = LivestockService::dueVaccinationsBetween($today, $soon);
        $overdueVaccinations = LivestockService::dueVaccinationsBetween('1970-01-01', date('Y-m-d', strtotime('-1 day')));

        $lowStock = $pdo->query('SELECT * FROM inventory_items WHERE current_quantity <= reorder_threshold AND is_active = 1')->fetchAll();

        $ordersAwaitingAction = $pdo->query(
            "SELECT COUNT(*) AS c FROM orders WHERE status IN ('placed','awaiting_quotation_approval','confirmed','processing')"
        )->fetch()['c'];

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $financeSummary = \App\Models\FinancialTransaction::summary($monthStart, $monthEnd);

        $outstandingReceivables = $pdo->query(
            "SELECT COALESCE(SUM(amount - amount_paid),0) AS t FROM invoices WHERE status != 'void'"
        )->fetch()['t'];

        $tasksTodayStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM employee_tasks WHERE due_date = :today AND status != 'done'");
        $tasksTodayStmt->execute(['today' => $today]);
        $tasksToday = $tasksTodayStmt->fetch()['c'];

        View::render('admin/dashboard/index', [
            'animalsBySpecies' => $animalsBySpecies,
            'dueVaccinations' => $dueVaccinations,
            'overdueVaccinations' => $overdueVaccinations,
            'lowStock' => $lowStock,
            'ordersAwaitingAction' => $ordersAwaitingAction,
            'financeSummary' => $financeSummary,
            'outstandingReceivables' => $outstandingReceivables,
            'tasksToday' => $tasksToday,
        ]);
    }
}
