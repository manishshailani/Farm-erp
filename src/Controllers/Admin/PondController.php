<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Pond;
use App\Models\PondStockingBatch;
use App\Models\PondWaterQualityReading;
use App\Services\PondService;

final class PondController
{
    public function index(Request $request, array $args): void
    {
        View::render('admin/pond/index', [
            'ponds' => Pond::withActiveStockingCounts(),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function storePond(Request $request, array $args): void
    {
        $data = $request->all();
        $v = new Validator($data);
        $v->required('name', 'Pond name')
            ->numeric('surface_area_sqm', 'Surface area')
            ->numeric('estimated_depth_m', 'Estimated depth');
        if ($v->fails()) {
            Session::flash('error', 'Pond name is required.');
            Redirect::to('/pond');
            return;
        }

        try {
            $id = PondService::createPond($data, Auth::id());
            Session::flash('success', 'Pond created.');
            Redirect::to('/pond/' . $id);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Redirect::to('/pond');
        }
    }

    public function show(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $pond = Pond::find($id);
        if ($pond === null) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }

        View::render('admin/pond/show', [
            'pond' => $pond,
            'batches' => PondStockingBatch::forPond($id),
            'readings' => PondWaterQualityReading::forPond($id),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function storeStockingBatch(Request $request, array $args): void
    {
        $pondId = (int) $args['id'];
        $data = $request->all();
        $data['pond_id'] = $pondId;

        try {
            PondService::addStockingBatch($data, Auth::id());
            Session::flash('success', 'Stocking batch recorded.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/pond/' . $pondId);
    }

    public function harvestBatch(Request $request, array $args): void
    {
        $pondId = (int) $args['id'];
        $batchId = (int) $args['batchId'];
        try {
            PondService::recordHarvest($batchId, $request->all(), Auth::id());
            Session::flash('success', 'Batch marked as harvested.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/pond/' . $pondId);
    }

    public function loseBatch(Request $request, array $args): void
    {
        $pondId = (int) $args['id'];
        $batchId = (int) $args['batchId'];
        try {
            PondService::markBatchLost($batchId, $request->string('notes') ?: null, Auth::id());
            Session::flash('success', 'Batch marked as lost.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/pond/' . $pondId);
    }

    public function storeWaterQualityReading(Request $request, array $args): void
    {
        $pondId = (int) $args['id'];
        $data = $request->all();
        $data['pond_id'] = $pondId;

        try {
            PondService::recordWaterQualityReading($data, Auth::id());
            Session::flash('success', 'Water quality reading recorded.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/pond/' . $pondId);
    }
}
