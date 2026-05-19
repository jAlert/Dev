<?php

use App\Livewire\Forms\LoginForm;
use App\Models\LoginSlide;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.login')] class extends Component {
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        $user = Auth::user();

        if ($user->two_factor_confirmed_at) {
            $remember = $this->form->remember;
            $userId = $user->id;
            Auth::logout();
            session(['2fa_challenge_user' => $userId, '2fa_challenge_remember' => $remember]);
            $this->redirect(route('2fa.challenge'), navigate: false);
            return;
        }

        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function with(): array
    {
        $slides = LoginSlide::active()->ordered()->get()->map(fn($s) => [
            'title' => $s->title,
            'subtitle' => $s->subtitle,
            'image' => $s->image_path ? Storage::url($s->image_path) : null,
        ])->values()->toArray();

        return ['slides' => $slides];
    }
}; ?>

<div class="w-full max-w-5xl mx-auto bg-white rounded-2xl shadow-2xl overflow-hidden flex min-h-[560px]">

    {{-- ── LEFT: Login Form ─────────────────────────────────── --}}
    <div class="w-full md:w-[45%] flex flex-col justify-center px-10 py-12 bg-white">

        {{-- Logo --}}
        <div class="flex flex-col items-center mb-8 text-center">
            <img src="/images/bmb_denr_logo1.png" alt="DENR-BMB Logo" class="h-32 w-auto" />
            <span class="text-2xl font-bold text-gray-800 tracking-tight leading-snug">Policy Review and Monitoring
                System</span>
        </div>

        {{-- Heading --}}
        <h1 class="text-xl font-bold text-gray-500 mb-1">Log in to your Account</h1>
        <p class="text-sm text-gray-500 mb-7">Welcome back! Please sign in to continue.</p>

        {{-- Session status --}}
        <x-auth-session-status class="mb-4" :status="session('status')" />

        {{-- Form --}}
        <form wire:submit="login" class="flex flex-col gap-4">

            {{-- Email --}}
            <div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <input wire:model="form.email" id="email" type="email" name="email" required autofocus
                        autocomplete="username" placeholder="Email"
                        class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition" />
                </div>
                <x-input-error :messages="$errors->get('form.email')" class="mt-1.5" />
            </div>

            {{-- Password --}}
            <div x-data="{ show: false }">
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </span>
                    <input wire:model="form.password" id="password" name="password" :type="show ? 'text' : 'password'"
                        required autocomplete="current-password" placeholder="Password"
                        class="w-full pl-9 pr-10 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition" />
                    <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600 transition">
                        <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21" />
                        </svg>
                        <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            style="display:none">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('form.password')" class="mt-1.5" />
            </div>

            {{-- Remember me + Forgot password --}}
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                        class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    <span class="text-sm text-gray-600">Remember me</span>
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate
                        class="text-sm text-blue-600 hover:text-blue-700 font-medium transition">
                        Forgot Password?
                    </a>
                @endif
            </div>

            {{-- Submit --}}
            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg text-sm transition focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <span wire:loading.remove wire:target="login">Log In</span>
                <span wire:loading wire:target="login">Signing in…</span>
            </button>
        </form>

    </div>

    {{-- ── RIGHT: Hero Carousel ────────────────────────────── --}}
    <div class="hidden md:flex md:w-[55%] bg-blue-600 relative overflow-hidden select-none" x-data="{
             slides: @js($slides),
             current: 0,
             startX: 0,
             get total() { return this.slides.length },
             init() {
                 if (this.total > 1) {
                     setInterval(() => { this.current = (this.current + 1) % this.total }, 5000)
                 }
             },
             next() { this.current = (this.current + 1) % this.total },
             prev() { this.current = (this.current - 1 + this.total) % this.total },
             onTouchStart(e) { this.startX = e.touches[0].clientX },
             onTouchEnd(e) {
                 const dx = e.changedTouches[0].clientX - this.startX
                 if (dx < -50) this.next()
                 if (dx > 50)  this.prev()
             }
         }" @touchstart="onTouchStart($event)" @touchend="onTouchEnd($event)">

        {{-- Background slides (full cover) --}}
        <template x-for="(slide, i) in slides" :key="i">
            <div x-show="current === i" x-transition:enter="transition ease-in-out duration-700"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in-out duration-700" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" class="absolute inset-0">
                <template x-if="slide.image">
                    <img :src="slide.image" :alt="slide.title" class="w-full h-full object-cover" />
                </template>
                <template x-if="!slide.image">
                    <div class="w-full h-full bg-blue-600"></div>
                </template>
            </div>
        </template>

        {{-- Fallback when no slides --}}
        <template x-if="total === 0">
            <div class="absolute inset-0 bg-blue-600 flex items-center justify-center">
                <svg class="w-48 h-40 opacity-30" viewBox="0 0 220 170" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="60" y="20" width="120" height="90" rx="8" fill="white" fill-opacity="0.4" stroke="white"
                        stroke-opacity="0.6" stroke-width="1.5" />
                    <rect x="72" y="34" width="55" height="6" rx="3" fill="white" fill-opacity="0.7" />
                    <rect x="72" y="46" width="40" height="5" rx="2.5" fill="white" fill-opacity="0.5" />
                    <rect x="72" y="60" width="96" height="5" rx="2.5" fill="white" fill-opacity="0.3" />
                    <rect x="72" y="70" width="80" height="5" rx="2.5" fill="white" fill-opacity="0.3" />
                    <rect x="72" y="80" width="88" height="5" rx="2.5" fill="white" fill-opacity="0.3" />
                </svg>
            </div>
        </template>

        {{-- Dot indicators — inset overlay so positioning is reliable --}}
        <div class="absolute inset-0 flex flex-col justify-end z-20 pointer-events-none" style="padding-bottom: 40px;">
            <div x-show="total > 1" class="flex items-center justify-center gap-2 pointer-events-auto">
                <template x-for="(_, i) in slides" :key="'dot-'+i">
                    <button @click="current = i"
                        :class="current === i ? 'w-5 bg-white' : 'w-2 bg-white/50 hover:bg-white/70'"
                        class="h-2 rounded-full transition-all duration-300 focus:outline-none">
                    </button>
                </template>
            </div>
        </div>

        {{-- Swipe arrows --}}
        <button x-show="total > 1" @click="prev()"
            class="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-black/20 hover:bg-black/30 flex items-center justify-center text-white transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>
        <button x-show="total > 1" @click="next()"
            class="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-black/20 hover:bg-black/30 flex items-center justify-center text-white transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>
</div>