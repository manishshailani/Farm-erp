<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\CartService;

final class CartController
{
    public function show(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        $cart = CartService::cartForCustomer((int) $customer['id']);
        View::render('shop/cart', [
            'items' => CartService::items((int) $cart['id']),
        ], 'layouts/shop');
    }

    public function add(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        $cart = CartService::cartForCustomer((int) $customer['id']);
        try {
            CartService::addItem((int) $cart['id'], $request->int('product_id'), max(1.0, $request->float('quantity', 1)));
            Session::flash('success', 'Added to cart.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        Redirect::to('/shop/cart');
    }

    public function update(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        $cart = CartService::cartForCustomer((int) $customer['id']);
        CartService::updateQuantity((int) $cart['id'], (int) $args['itemId'], $request->float('quantity'));
        Redirect::to('/shop/cart');
    }

    public function remove(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        $cart = CartService::cartForCustomer((int) $customer['id']);
        CartService::removeItem((int) $cart['id'], (int) $args['itemId']);
        Redirect::to('/shop/cart');
    }
}
