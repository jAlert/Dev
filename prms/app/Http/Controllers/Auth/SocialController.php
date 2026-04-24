<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'password' => bcrypt(Str::random(16)), // Random password since they login via Google
                ]
            );

            if (! $user->is_active) {
                return redirect('/login')->withErrors(['email' => 'Your account has been deactivated. Please contact an administrator.']);
            }

            // Update Google profile data for existing active users
            if (! $user->wasRecentlyCreated) {
                $user->update(['name' => $googleUser->getName(), 'google_id' => $googleUser->getId()]);
            }

            Auth::login($user);

            return redirect()->intended(route('dashboard', absolute: false));

        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['email' => 'Unable to authenticate with Google. ' . $e->getMessage()]);
        }
    }
}
