<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use PragmaRX\Google2FA\Google2FA;

new #[Layout('layouts.guest')] class extends Component
{
    public string $totpCode = '';

    public function mount(): void
    {
        if (!session()->has('2fa_challenge_user')) {
            $this->redirect(route('login'), navigate: false);
        }
    }

    public function verify(): void
    {
        $this->validate(['totpCode' => 'required|digits:6'], [], ['totpCode' => 'authentication code']);

        $user = User::find(session('2fa_challenge_user'));

        if (!$user || !$user->two_factor_secret) {
            session()->forget(['2fa_challenge_user', '2fa_challenge_remember']);
            $this->redirect(route('login'), navigate: false);
            return;
        }

        $google2fa = new Google2FA();

        if (!$google2fa->verifyKey(decrypt($user->two_factor_secret), $this->totpCode)) {
            $this->addError('totpCode', 'Invalid authentication code. Please try again.');
            return;
        }

        $remember = session('2fa_challenge_remember', false);
        session()->forget(['2fa_challenge_user', '2fa_challenge_remember']);

        Auth::login($user, $remember);
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: false);
    }

    public function back(): void
    {
        session()->forget(['2fa_challenge_user', '2fa_challenge_remember']);
        $this->redirect(route('login'), navigate: false);
    }
}; ?>

<div>
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-100 rounded-full mb-3">
            <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Two-Factor Authentication</h3>
        <p class="text-sm text-gray-500 mt-1">Enter the 6-digit code from your authenticator app.</p>
    </div>

    <form wire:submit="verify">
        <div>
            <x-input-label for="totpCode" value="Authentication Code" />
            <input wire:model="totpCode"
                   id="totpCode"
                   type="text"
                   inputmode="numeric"
                   maxlength="6"
                   placeholder="000000"
                   autocomplete="one-time-code"
                   autofocus
                   class="block mt-1 w-full text-center text-2xl font-mono tracking-[0.6em] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-3" />
            <x-input-error :messages="$errors->get('totpCode')" class="mt-2" />
        </div>

        <div class="mt-5">
            <x-primary-button class="w-full justify-center">Verify</x-primary-button>
        </div>

        <div class="mt-3 text-center">
            <button type="button" wire:click="back" class="text-sm text-gray-500 hover:text-gray-700 underline">
                ← Back to login
            </button>
        </div>
    </form>
</div>
