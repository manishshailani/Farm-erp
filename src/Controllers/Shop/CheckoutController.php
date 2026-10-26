<?php

declare(strict_types=1);

namespace App\Controllers\Shop;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\Customer;
use App\Services\CartService;
use App\Services\OrderService;

final class CheckoutController
{
    public function show(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        $cart = CartService::cartForCustomer((int) $customer['id']);
        $items = CartService::items((int) $cart['id']);
        if ($items === []) {
            Redirect::to('/shop/cart');
            return;
        }
        View::render('shop/checkout', [
            'items' => $items,
            'addresses' => Customer::addresses((int) $customer['id']),
        ], 'layouts/shop');
    }

    public function placeOrder(Request $request, array $args): void
    {
        $customer = ShopAuth::currentCustomer();
        $cart = CartService::cartForCustomer((int) $customer['id']);
        $cartItems = CartService::items((int) $cart['id']);

        if ($cartItems === []) {
            Session::flash('error', 'Your cart is empty.');
            Redirect::to('/shop/cart');
            return;
        }

        $items = array_map(static fn (array $row) => [
            'product_id' => (int) $row['product_id'],
            'quantity' => (float) $row['quantity'],
        ], $cartItems);

        $deliveryMethod = $request->string('delivery_method', 'pickup');
        $addressId = $deliveryMethod === 'delivery' ? $request->int('address_id') : null;
        $deliveryCharge = $deliveryMethod === 'delivery' ? 50.0 : 0.0; // flat rate; see known limitations

        try {
            $orderId = OrderService::placeOrder(
                (int) $customer['id'],
                $items,
                $deliveryMethod,
                $addressId,
                $deliveryCharge,
                $request->string('notes') ?: null,
                Auth::id(),
            );
            CartService::clear((int) $cart['id']);
            Session::flash('success', 'Order placed successfully.');
            Redirect::to('/shop/account/orders/' . $orderId);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            Redirect::to('/shop/checkout');
        }
    }
}
