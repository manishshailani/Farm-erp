<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Core\Request;
use App\Core\View;
use App\Models\Product;

final class CatalogueController
{
    public function index(Request $request, array $args): void
    {
        $category = $request->string('category') ?: null;
        View::render('shop/catalogue', [
            'products' => Product::activeCatalogue($category),
            'categories' => Product::categories(),
            'selectedCategory' => $category,
        ], 'layouts/shop');
    }

    public function show(Request $request, array $args): void
    {
        $product = Product::find((int) $args['id']);
        if ($product === null || !$product['is_active']) {
            http_response_code(404);
            View::render('errors/404', [], '');
            return;
        }
        View::render('shop/product', ['product' => $product], 'layouts/shop');
    }
}
