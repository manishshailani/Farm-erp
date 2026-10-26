<?php

declare(strict_types=1);

/**
 * Reference/master data: species+breeds, inventory categories and a
 * default cash account. Deliberately small -- the farm owner adds further
 * species/breeds/categories through the admin UI rather than editing seed
 * files, which only need to cover enough to demo/test every module.
 */

use App\Core\Database;

return static function (): void {
    $pdo = Database::connection();
    $driver = Database::driver();
    $ignore = $driver === 'sqlite' ? 'OR IGNORE' : 'IGNORE';
    $now = date('Y-m-d H:i:s');

    $speciesBreeds = [
        'Goat' => ['Jamunapari', 'Beetal', 'Barbari', 'Sirohi'],
        'Buffalo' => ['Murrah', 'Bhadawari'],
        'Cattle' => ['Sahiwal', 'Gir', 'Crossbred'],
        'Poultry (Layer)' => ['Hy-Line Brown', 'BV-300'],
        'Poultry (Broiler)' => ['Cobb 500'],
        'Fish' => ['Rohu', 'Catla', 'Pangasius'],
    ];

    foreach ($speciesBreeds as $species => $breeds) {
        $trackingLevel = $species === 'Fish' || str_starts_with($species, 'Poultry') ? 'batch' : 'individual';
        $pdo->prepare("INSERT {$ignore} INTO species (name, tracking_level) VALUES (:n, :t)")
            ->execute(['n' => $species, 't' => $trackingLevel]);
        $speciesId = (int) $pdo->query('SELECT id FROM species WHERE name = ' . $pdo->quote($species))->fetchColumn();
        foreach ($breeds as $breed) {
            $pdo->prepare("INSERT {$ignore} INTO breeds (species_id, name) VALUES (:s, :n)")
                ->execute(['s' => $speciesId, 'n' => $breed]);
        }
    }

    $categories = ['Animal Feed', 'Poultry Feed', 'Fish Feed', 'Veterinary Medicine', 'Vaccines', 'Seeds & Saplings', 'Fertilisers', 'Packaging Material', 'Farm Produce'];
    foreach ($categories as $category) {
        $pdo->prepare("INSERT {$ignore} INTO inventory_categories (name) VALUES (:n)")->execute(['n' => $category]);
    }

    $pdo->prepare("INSERT {$ignore} INTO accounts (name, type, opening_balance, current_balance) VALUES ('Cash in Hand', 'cash', 0, 0)")->execute();
    $pdo->prepare("INSERT {$ignore} INTO accounts (name, type, opening_balance, current_balance) VALUES ('Farm Bank Account', 'bank', 0, 0)")->execute();

    $settings = [
        'farm_name' => 'Integrated Farm',
        'farm_address' => '',
        'farm_phone' => '',
        'farm_email' => '',
        'currency' => 'INR',
        'date_format' => 'd-m-Y',
        'timezone' => 'Asia/Kolkata',
    ];
    foreach ($settings as $key => $value) {
        $pdo->prepare("INSERT {$ignore} INTO settings (`key`, `value`) VALUES (:k, :v)")->execute(['k' => $key, 'v' => $value]);
    }
};
