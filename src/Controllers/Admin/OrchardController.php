<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\InventoryItem;
use App\Models\OrchardBlock;
use App\Models\OrchardHarvest;
use App\Models\OrchardTree;
use App\Services\OrchardService;

final class OrchardController
{
    public function index(Request $request, array $args): void
    {
        View::render('admin/orchard/index', [
            'blocks' => OrchardBlock::withTreeCounts(),
            'totals' => OrchardHarvest::totals(),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function storeBlock(Request $request, array $args): void
    {
        $data = $request->all();
        $v = new Validator($data);
        $v->required('name', 'Block name')->required('species', 'Species');
        if ($v->fails()) {
            Session::flash('error', 'Block name and species are required.');
            Redirect::to('/orchard');
            return;
        }

        try {
            $id = OrchardService::createBlock($data, Auth::id());
            Session::flash('success', 'Orchard block created.');
            Redirect::to('/orchard/' . $id);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Redirect::to('/orchard');
        }
    }

    public function show(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $block = OrchardBlock::find($id);
        if ($block === null) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }

        View::render('admin/orchard/show', [
            'block' => $block,
            'trees' => OrchardTree::forBlock($id),
            'harvests' => OrchardHarvest::forBlock($id),
            'inventoryItems' => InventoryItem::withCategory(),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function storeTree(Request $request, array $args): void
    {
        $blockId = (int) $args['id'];
        $data = $request->all();
        $data['block_id'] = $blockId;

        try {
            OrchardService::plantTree($data, Auth::id());
            Session::flash('success', 'Tree added.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/orchard/' . $blockId);
    }

    public function updateTreeStatus(Request $request, array $args): void
    {
        $blockId = (int) $args['id'];
        $treeId = (int) $args['treeId'];
        try {
            OrchardService::setTreeStatus($treeId, $request->string('status'), Auth::id());
            Session::flash('success', 'Tree status updated.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/orchard/' . $blockId);
    }

    public function storeHarvest(Request $request, array $args): void
    {
        $blockId = (int) $args['id'];
        $data = $request->all();
        $data['block_id'] = $blockId;

        try {
            OrchardService::recordHarvest($data, Auth::id());
            Session::flash('success', 'Harvest recorded.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/orchard/' . $blockId);
    }
}
