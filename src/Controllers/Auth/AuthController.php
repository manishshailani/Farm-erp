<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Models\User;
use App\Services\LoginThrottle;

final class AuthController
{
    public function showLogin(Request $request, array $args): void
    {
        if (Auth::check()) {
            Redirect::to('/dashboard');
            return;
        }
        View::render('auth/login', ['error' => Session::pullFlash('error')], 'layouts/guest');
    }

    public function login(Request $request, array $args): void
    {
        $email = $request->string('email');
        $password = $request->string('password');
        $ip = $request->ip();

        if (LoginThrottle::tooManyAttempts($email, $ip)) {
            Session::flash('error', 'Too many failed login attempts. Please wait a few minutes before trying again.');
            Redirect::to('/login');
            return;
        }

        $ok = Auth::attempt($email, $password);
        LoginThrottle::record($email, $ip, $ok);

        if (!$ok) {
            Session::flash('error', 'Invalid email or password.');
            Redirect::to('/login');
            return;
        }

        $userId = Auth::id();
        $roles = $userId !== null ? User::roleNames($userId) : [];
        if (in_array('Customer', $roles, true) && count($roles) === 1) {
            Redirect::to('/shop/account/orders');
            return;
        }
        Redirect::to('/dashboard');
    }

    public function logout(Request $request, array $args): void
    {
        Auth::logout();
        Redirect::to('/login');
    }
}
