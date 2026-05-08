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
    <div class="hidden md:flex md:w-[55%] bg-blue-600 flex-col items-center justify-center relative overflow-hidden select-none"
        x-data="{
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

        {{-- Background circles (decorative) --}}
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="w-80 h-80 rounded-full bg-blue-500/30"></div>
        </div>
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="w-56 h-56 rounded-full bg-blue-500/30"></div>
        </div>

        {{-- Slide content --}}
        <div class="relative z-10 flex flex-col items-center px-10 text-center w-full">

            {{-- Image area --}}
            <div class="w-64 h-48 flex items-center justify-center mb-8">
                <template x-if="total === 0">
                    {{-- Default illustration when no slides configured --}}
                    <svg class="w-56 h-44 opacity-90" viewBox="0 0 220 170" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <rect x="60" y="20" width="120" height="90" rx="8" fill="white" fill-opacity="0.15"
                            stroke="white" stroke-opacity="0.4" stroke-width="1.5" />
                        <rect x="72" y="34" width="55" height="6" rx="3" fill="white" fill-opacity="0.5" />
                        <rect x="72" y="46" width="40" height="5" rx="2.5" fill="white" fill-opacity="0.3" />
                        <rect x="72" y="60" width="96" height="5" rx="2.5" fill="white" fill-opacity="0.2" />
                        <rect x="72" y="70" width="80" height="5" rx="2.5" fill="white" fill-opacity="0.2" />
                        <rect x="72" y="80" width="88" height="5" rx="2.5" fill="white" fill-opacity="0.2" />
                        <circle cx="44" cy="78" r="22" fill="white" fill-opacity="0.15" stroke="white"
                            stroke-opacity="0.4" stroke-width="1.5" />
                        <path d="M36 78l5 5 9-9" stroke="white" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <circle cx="176" cy="50" r="18" fill="white" fill-opacity="0.15" stroke="white"
                            stroke-opacity="0.4" stroke-width="1.5" />
                        <path d="M168 50h16M176 42v16" stroke="white" stroke-width="2" stroke-linecap="round" />
                        <circle cx="150" cy="135" r="16" fill="white" fill-opacity="0.15" stroke="white"
                            stroke-opacity="0.4" stroke-width="1.5" />
                        <path d="M144 135l4 4 8-8" stroke="white" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </template>
                <template x-if="total > 0">
                    <template x-for="(slide, i) in slides" :key="i">
                        <div x-show="current === i" x-transition:enter="transition ease-out duration-500"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            class="absolute w-64 h-48 flex items-center justify-center">
                            <template x-if="slide.image">
                                <img :src="slide.image" :alt="slide.title"
                                    class="max-w-full max-h-full object-contain rounded-xl shadow-lg" />
                            </template>
                            <template x-if="!slide.image">
                                <svg class="w-40 h-32 opacity-60" viewBox="0 0 160 120" fill="none">
                                    <rect x="10" y="10" width="140" height="100" rx="8" fill="white" fill-opacity="0.2"
                                        stroke="white" stroke-opacity="0.4" stroke-width="1.5" />
                                    <path d="M10 85l35-30 25 22 30-35 50 48" stroke="white" stroke-opacity="0.5"
                                        stroke-width="2" stroke-linejoin="round" />
                                    <circle cx="55" cy="45" r="12" fill="white" fill-opacity="0.3" />
                                </svg>
                            </template>
                        </div>
                    </template>
                </template>
            </div>

            {{-- Text content --}}
            <template x-if="total === 0">
                <div>
                    <p class="text-white font-bold text-lg leading-snug">DENR-BMB Policy Review<br>and Monitoring System
                    </p>
                    <p class="text-blue-200 text-sm mt-2">One-stop policy tracking and management.</p>
                </div>
            </template>
            <template x-if="total > 0">
                <template x-for="(slide, i) in slides" :key="'text-'+i">
                    <div x-show="current === i" x-transition:enter="transition ease-out duration-500"
                        x-transition:enter-start="opacity-0 translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="text-white font-bold text-lg leading-snug" x-text="slide.title"></p>
                        <p class="text-blue-200 text-sm mt-2" x-text="slide.subtitle ?? ''"></p>
                    </div>
                </template>
            </template>

            {{-- Dot indicators --}}
            <template x-if="total > 1">
                <div class="flex items-center gap-2 mt-6">
                    <template x-for="(_, i) in slides" :key="'dot-'+i">
                        <button @click="current = i"
                            :class="current === i ? 'w-5 bg-white' : 'w-2 bg-white/40 hover:bg-white/60'"
                            class="h-2 rounded-full transition-all duration-300 focus:outline-none">
                        </button>
                    </template>
                </div>
            </template>
        </div>

        {{-- Swipe arrows (visible on hover) --}}
        <template x-if="total > 1">
            <div>
                <button @click="prev()"
                    class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button @click="next()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </template>
    </div>
</div>