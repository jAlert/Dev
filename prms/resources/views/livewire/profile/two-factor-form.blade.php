<?php

use Livewire\Volt\Component;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

new class extends Component {

    public string $uiStep   = 'status'; // status | setup | disable
    public string $qrSvg    = '';
    public string $secret   = '';
    public string $code     = '';
    public string $message  = '';

    public function enableTwoFactor(): void
    {
        $google2fa = new Google2FA();
        $secret    = $google2fa->generateSecretKey();

        $otpauthUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            auth()->user()->email,
            $secret
        );

        $renderer     = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
        $writer       = new Writer($renderer);
        $this->qrSvg  = $writer->writeString($otpauthUrl);
        $this->secret = $secret;

        session(['2fa_temp_secret' => $secret]);

        $this->uiStep = 'setup';
        $this->code   = '';
    }

    public function confirmEnable(): void
    {
        $this->validate(['code' => 'required|digits:6'], [], ['code' => 'authentication code']);

        $secret = session('2fa_temp_secret');

        if (!$secret) {
            $this->uiStep = 'status';
            return;
        }

        $google2fa = new Google2FA();

        if (!$google2fa->verifyKey($secret, $this->code)) {
            $this->addError('code', 'Invalid code. Make sure your authenticator app time is correct.');
            return;
        }

        auth()->user()->update([
            'two_factor_secret'        => encrypt($secret),
            'two_factor_confirmed_at'  => now(),
        ]);

        session()->forget('2fa_temp_secret');
        $this->uiStep  = 'status';
        $this->code    = '';
        $this->message = 'Two-factor authentication has been enabled.';
    }

    public function showDisable(): void
    {
        $this->uiStep = 'disable';
        $this->code   = '';
    }

    public function confirmDisable(): void
    {
        $this->validate(['code' => 'required|digits:6'], [], ['code' => 'authentication code']);

        $user      = auth()->user();
        $google2fa = new Google2FA();

        if (!$google2fa->verifyKey(decrypt($user->two_factor_secret), $this->code)) {
            $this->addError('code', 'Invalid code.');
            return;
        }

        $user->update([
            'two_factor_secret'       => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->uiStep  = 'status';
        $this->code    = '';
        $this->message = 'Two-factor authentication has been disabled.';
    }

    public function cancel(): void
    {
        session()->forget('2fa_temp_secret');
        $this->uiStep = 'status';
        $this->code   = '';
    }

}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Two-Factor Authentication</h2>
        <p class="mt-1 text-sm text-gray-600">
            Add an extra layer of security using Google Authenticator or any TOTP app.
        </p>
    </header>

    @if($message)
        <div class="mt-4 px-4 py-3 rounded-lg text-sm font-medium
            {{ str_contains($message, 'enabled') ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-yellow-50 text-yellow-700 border border-yellow-200' }}">
            {{ $message }}
        </div>
    @endif

    {{-- STATUS --}}
    @if($uiStep === 'status')
        @if(auth()->user()->two_factor_confirmed_at)
            <div class="mt-5 flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-100 text-green-700 text-sm font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Enabled
                </span>
                <span class="text-xs text-gray-500">Since {{ auth()->user()->two_factor_confirmed_at->format('M d, Y') }}</span>
            </div>
            <div class="mt-4">
                <button wire:click="showDisable"
                        class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                    Disable 2FA
                </button>
            </div>
        @else
            <div class="mt-5 flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gray-100 text-gray-600 text-sm font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Not enabled
                </span>
            </div>
            <div class="mt-4">
                <button wire:click="enableTwoFactor"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Enable 2FA
                </button>
            </div>
        @endif
    @endif

    {{-- SETUP --}}
    @if($uiStep === 'setup')
        <div class="mt-5 space-y-4">
            <p class="text-sm text-gray-700 font-medium">
                1. Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
            </p>

            <div class="inline-block p-3 bg-white border-2 border-gray-200 rounded-xl shadow-sm">
                {!! $qrSvg !!}
            </div>

            <div>
                <p class="text-xs text-gray-500 mb-1">Or enter this key manually:</p>
                <code class="inline-block bg-gray-100 text-gray-800 text-sm px-3 py-1.5 rounded-lg font-mono tracking-widest select-all">
                    {{ $secret }}
                </code>
            </div>

            <p class="text-sm text-gray-700 font-medium">
                2. Enter the 6-digit code from your app to confirm:
            </p>

            <div>
                <input wire:model="code"
                       type="text"
                       inputmode="numeric"
                       maxlength="6"
                       placeholder="000000"
                       autocomplete="one-time-code"
                       class="block w-40 text-center text-xl font-mono tracking-[0.5em] rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2.5" />
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3 pt-1">
                <button wire:click="confirmEnable"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Confirm & Enable
                </button>
                <button wire:click="cancel"
                        class="px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    {{-- DISABLE --}}
    @if($uiStep === 'disable')
        <div class="mt-5 space-y-4">
            <p class="text-sm text-gray-700">
                Enter the 6-digit code from your authenticator app to disable 2FA:
            </p>

            <div>
                <input wire:model="code"
                       type="text"
                       inputmode="numeric"
                       maxlength="6"
                       placeholder="000000"
                       autocomplete="one-time-code"
                       class="block w-40 text-center text-xl font-mono tracking-[0.5em] rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-2.5" />
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3">
                <button wire:click="confirmDisable"
                        class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                    Confirm Disable
                </button>
                <button wire:click="cancel"
                        class="px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                    Cancel
                </button>
            </div>
        </div>
    @endif
</section>
