<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\InventoryItem;
use App\Services\InventoryService;

final class InventoryController
{
    public function index(Request $request, array $args): void
    {
        View::render('admin/inventory/index', [
            'items' => InventoryItem::withCategory(),
            'categories' => Database::connection()->query('SELECT * FROM inventory_categories ORDER BY name')->fetchAll(),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function store(Request $request, array $args): void
    {
        $pdo = Database::connection();
        $now = date('Y-m-d H:i:s');
        $sku = $request->string('sku');
        if ($sku === '') {
            $sku = 'ITEM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }
        $pdo->prepare(
            'INSERT INTO inventory_items (sku, name, category_id, unit, reorder_threshold, current_quantity, average_cost, storage_location, allow_negative_stock, is_active, created_at, updated_at)
             VALUES (:sku, :name, :cat, :unit, :threshold, 0, 0, :loc, :allow_neg, 1, :c, :u)'
        )->execute([
            'sku' => $sku,
            'name' => $request->string('name'),
            'cat' => $request->int('category_id') ?: null,
            'unit' => $request->string('unit', 'piece'),
            'threshold' => $request->float('reorder_threshold'),
            'loc' => $request->string('storage_location') ?: null,
            'allow_neg' => $request->bool('allow_negative_stock') ? 1 : 0,
            'c' => $now,
            'u' => $now,
        ]);
        Session::flash('success', 'Inventory item created.');
        Redirect::to('/inventory');
    }

    public function show(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $item = InventoryItem::find($id);
        if ($item === null) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }
        View::render('admin/inventory/show', [
            'item' => $item,
            'batches' => InventoryItem::batches($id),
            'movements' => InventoryItem::movements($id),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function receive(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        try {
            InventoryService::receiveStock(
                $id,
                $request->float('quantity'),
                $request->float('unit_cost'),
                $request->string('received_date', date('Y-m-d')),
                $request->string('batch_number') ?: null,
                $request->string('supplier_name') ?: null,
                $request->string('expiry_date') ?: null,
                Auth::id(),
            );
            Session::flash('success', 'Stock received.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/inventory/' . $id);
    }

    public function adjust(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        try {
            InventoryService::adjustStock($id, $request->float('new_quantity'), $request->string('reason', 'Manual adjustment'), Auth::id());
            Session::flash('success', 'Stock adjusted.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/inventory/' . $id);
    }
}
