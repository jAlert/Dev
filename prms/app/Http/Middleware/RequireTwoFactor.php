<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user &&
            $user->two_factor_confirmed_at &&
            session('2fa_in_progress') &&
            session('2fa_verified') !== $user->id
        ) {
            // Allow the login route and Livewire internal requests (TOTP form submission)
            if (!$request->routeIs('login') && !$request->is('livewire/update')) {
                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}
