<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Animal;
use App\Models\Breed;
use App\Models\InventoryItem;
use App\Models\Species;
use App\Services\LivestockService;

final class LivestockController
{
    public function index(Request $request, array $args): void
    {
        $filters = [
            'species_id' => $request->string('species_id'),
            'status' => $request->string('status'),
            'q' => $request->string('q'),
        ];
        View::render('admin/livestock/index', [
            'animals' => Animal::search($filters),
            'species' => Species::all('name ASC'),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, array $args): void
    {
        View::render('admin/livestock/create', [
            'species' => Species::all('name ASC'),
            'errors' => Session::pullFlash('errors', []),
            'old' => Session::pullFlash('old', []),
        ]);
    }

    public function store(Request $request, array $args): void
    {
        $data = $request->all();
        $v = new Validator($data);
        $v->required('animal_code', 'Animal code')
            ->required('species_id', 'Species')
            ->required('sex', 'Sex')
            ->in('sex', ['male', 'female', 'unknown'], 'Sex')
            ->required('source', 'Source')
            ->date('date_of_birth', 'Date of birth')
            ->date('purchase_date', 'Purchase date')
            ->numeric('purchase_cost', 'Purchase cost')
            ->numeric('current_weight_kg', 'Current weight');

        if ($v->fails()) {
            Session::flash('errors', $v->errors());
            Session::flash('old', $data);
            Redirect::to('/livestock/create');
            return;
        }

        $id = LivestockService::registerAnimal($data, Auth::id());
        Session::flash('success', 'Animal registered successfully.');
        Redirect::to('/livestock/' . $id);
    }

    public function show(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $animal = Animal::withRelations($id);
        if ($animal === null) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }

        View::render('admin/livestock/show', [
            'animal' => $animal,
            'activeTag' => Animal::activeTag($id),
            'weightHistory' => Animal::weightHistory($id),
            'lifecycleEvents' => Animal::lifecycleEvents($id),
            'vaccinations' => Animal::vaccinationHistory($id),
            'inventoryItems' => InventoryItem::withCategory(),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function storeWeight(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $weight = $request->float('weight_kg');
        if ($weight <= 0) {
            Session::flash('error', 'Weight must be greater than zero.');
            Redirect::to('/livestock/' . $id);
            return;
        }
        LivestockService::recordWeight($id, $weight, date('Y-m-d H:i:s'), $request->string('notes') ?: null, Auth::id());
        Session::flash('success', 'Weight recorded.');
        Redirect::to('/livestock/' . $id);
    }

    public function storeVaccination(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $data = $request->all();
        $data['animal_id'] = $id;

        $v = new Validator($data);
        $v->required('vaccine_name', 'Vaccine/treatment name')->required('administered_date', 'Administered date')->date('administered_date', 'Administered date');
        if ($v->fails()) {
            Session::flash('error', 'Please fill in the required vaccination fields.');
            Redirect::to('/livestock/' . $id);
            return;
        }

        try {
            LivestockService::recordVaccination($data, Auth::id());
            Session::flash('success', 'Vaccination recorded.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/livestock/' . $id);
    }

    public function storeTag(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $tag = $request->string('tag_number');
        try {
            LivestockService::assignTag($id, $tag, Auth::id());
            Session::flash('success', "Tag {$tag} assigned.");
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/livestock/' . $id);
    }

    public function dispose(Request $request, array $args): void
    {
        $id = (int) $args['id'];
        $type = $request->string('disposal_type');
        $reason = $request->string('reason');
        LivestockService::dispose($id, $type, $reason, Auth::id());
        Session::flash('success', 'Animal record updated.');
        Redirect::to('/livestock/' . $id);
    }

    /** JSON endpoint used by the breed <select> on the create form. */
    public function breedsForSpecies(Request $request, array $args): void
    {
        header('Content-Type: application/json');
        echo json_encode(Breed::forSpecies((int) $args['speciesId']));
    }
}
