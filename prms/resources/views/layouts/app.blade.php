<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] {
            display: none !important
        }
    </style>

    <!-- Global Alpine stores (toast + modal) — registered before Alpine initialises -->
    <script>
        document.addEventListener('alpine:init', () => {

            /* ── Toast store ─────────────────────────────────────────── */
            Alpine.store('toasts', {
                items: [],
                _seq: 1,
                add(type, message, { title = '', duration } = {}) {
                    const id = this._seq++
                    const d = duration !== undefined ? duration : (type === 'error' ? 0 : 5000)
                    const cfg = {
                        success: {
                            wrap: 'bg-white border-l-4 border-green-500 text-gray-800',
                            icon: '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                        },
                        error: {
                            wrap: 'bg-white border-l-4 border-red-500 text-gray-800',
                            icon: '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                        },
                        warning: {
                            wrap: 'bg-white border-l-4 border-amber-500 text-gray-800',
                            icon: '<svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
                        },
                        info: {
                            wrap: 'bg-white border-l-4 border-blue-500 text-gray-800',
                            icon: '<svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                        },
                    }
                    const c = cfg[type] || cfg.info
                    this.items.push({ id, type, title, message, wrap: c.wrap, icon: c.icon, visible: true })
                    if (d > 0) setTimeout(() => this.dismiss(id), d)
                    return id
                },
                dismiss(id) {
                    const t = this.items.find(x => x.id === id)
                    if (t) t.visible = false
                    setTimeout(() => { this.items = this.items.filter(x => x.id !== id) }, 350)
                },
            })

            /* ── Modal store ─────────────────────────────────────────── */
            Alpine.store('modal', {
                show: false,
                type: 'confirm',   // confirm | success | error | warning | info
                title: '',
                message: '',
                confirmLabel: 'Confirm',
                cancelLabel: 'Cancel',
                _resolve: null,
                open({ type = 'confirm', title = '', message = '', confirmLabel = 'Confirm', cancelLabel = 'Cancel' } = {}) {
                    this.type = type
                    this.title = title
                    this.message = message
                    this.confirmLabel = confirmLabel
                    this.cancelLabel = cancelLabel
                    this.show = true
                    return new Promise(r => { this._resolve = r })
                },
                answer(val) {
                    this.show = false
                    const r = this._resolve
                    this._resolve = null
                    r?.(val)
                },
            })

        })
    </script>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e1e2f">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Theme CSS Variables -->
    @php
        $themes = [
            'indigo' => ['bg' => '#1e1e2f', 'header' => '#161624', 'active' => '#2d2d44', 'accent' => '#6366f1'],
            'blue' => ['bg' => '#0d1b2e', 'header' => '#081221', 'active' => '#163354', 'accent' => '#3b82f6'],
            'green' => ['bg' => '#0f1f18', 'header' => '#081510', 'active' => '#163828', 'accent' => '#22c55e'],
            'rose' => ['bg' => '#2a0f16', 'header' => '#1e0a0d', 'active' => '#4a1020', 'accent' => '#f43f5e'],
            'amber' => ['bg' => '#1f1a0d', 'header' => '#141005', 'active' => '#3d3010', 'accent' => '#f59e0b'],
            'slate' => ['bg' => '#1a1f2e', 'header' => '#13172a', 'active' => '#2d3548', 'accent' => '#64748b'],
        ];
        $t = $themes[auth()->user()?->theme ?? 'indigo'] ?? $themes['indigo'];
    @endphp
    <style>
        :root {
            --sidebar-bg:
                {{ $t['bg'] }}
            ;
            --sidebar-header-bg:
                {{ $t['header'] }}
            ;
            --sidebar-active-bg:
                {{ $t['active'] }}
            ;
            --accent:
                {{ $t['accent'] }}
            ;
        }
    </style>
</head>

<body class="font-sans antialiased text-gray-900 bg-gray-50" x-data="{ sidebarOpen: false }">

    <!-- Mobile Overlay -->
    <div x-show="sidebarOpen" x-transition.opacity x-cloak @click="sidebarOpen = false"
        class="fixed inset-0 bg-black/50 z-30 lg:hidden"></div>

    <!-- Sidebar: always fixed from top-0, overlaps top bar -->
    <div class="fixed top-0 left-0 h-full z-40 transition-transform duration-200 ease-in-out"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <livewire:layout.navigation />
    </div>

    <!-- Main area: offset right of sidebar on desktop -->
    <div class="lg:ml-64 flex flex-col h-screen overflow-hidden">

        <!-- Top Bar -->
        @php
            $topbarUnread = auth()->user()?->unreadNotifications()->latest()->take(10)->get() ?? collect();
            $topbarUnreadCount = $topbarUnread->count();
        @endphp
        <div
            class="flex-shrink-0 h-14 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 shadow-sm relative z-20">
            <!-- Left: mobile hamburger + brand -->
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-1.5 rounded hover:bg-gray-100 transition text-gray-600"
                    aria-label="Toggle sidebar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button><!--<span class="font-bold text-gray-500 tracking-wider text-md"></span>-->
                <h1 class="flex items-center text-sm sm:text-base font-bold tracking-tight text-gray-800 truncate"
                    title="DENR-BMB Policy Review and Monitoring System (PRMS)">
                    <span class="sm:inline truncate">DENR-BMB Policy Review and Monitoring System (PRMS)</span>
                    <span class="sm:hidden">PRMS</span>
                    <span
                        class="inline-flex ms-3 px-2 py-1 ring-1 ring-inset ring-blue-200 text-white text-xs font-medium rounded bg-blue-800">alpha</span>
                </h1>

            </div>

            <!-- Right: bell + user profile -->
            <div class="flex items-center gap-1">

                <!-- Notification Bell -->
                <div class="relative" x-data="{ bellOpen: false }" @click.outside="bellOpen = false">
                    <button @click="bellOpen = !bellOpen"
                        class="relative p-2 text-gray-500 hover:text-gray-800 hover:bg-gray-100 rounded-full transition"
                        aria-label="Notifications" title="Notifications">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-2.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        @if($topbarUnreadCount > 0)
                            <span
                                class="absolute top-1 right-1 bg-red-500 text-white text-[8px] rounded-full w-3.5 h-3.5 flex items-center justify-center font-bold leading-none">
                                {{ $topbarUnreadCount > 9 ? '9+' : $topbarUnreadCount }}
                            </span>
                        @endif
                    </button>

                    <div x-show="bellOpen" x-transition x-cloak
                        class="absolute top-11 right-0 w-72 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-2.5 border-b bg-gray-50">
                            <span class="text-xs font-bold text-gray-700 uppercase tracking-wide">Notifications</span>
                            @if($topbarUnreadCount > 0)
                                <form method="POST" action="{{ route('notifications.markAllRead') }}">
                                    @csrf
                                    <button type="submit"
                                        class="text-[10px] text-indigo-600 hover:underline font-medium">Mark all
                                        read</button>
                                </form>
                            @endif
                        </div>
                        <div class="max-h-72 overflow-y-auto divide-y divide-gray-100">
                            @forelse($topbarUnread as $notif)
                                @php $ndata = $notif->data; @endphp
                                <a href="{{ route('notifications.open', $notif->id) }}"
                                    class="flex items-start gap-3 px-4 py-3 hover:bg-indigo-50 transition-colors">
                                    <span class="mt-1 w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                                    <span class="text-xs text-gray-700 leading-snug">{{ $ndata['message'] ?? '' }}</span>
                                </a>
                            @empty
                                <div class="px-4 py-6 text-center text-xs text-gray-400 italic">No new notifications</div>
                            @endforelse
                        </div>
                        <div class="border-t px-4 py-2.5 bg-gray-50">
                            <a href="{{ route('builder.notifications') }}"
                                class="text-xs text-indigo-600 hover:underline font-medium">
                                View all notifications →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div class="relative" x-data="{ profileOpen: false }" @click.outside="profileOpen = false">
                    <button @click="profileOpen = !profileOpen" aria-label="User menu"
                        class="flex items-center gap-2 pl-2 pr-1 py-1.5 rounded-lg hover:bg-gray-100 transition ml-1">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                            style="background-color: var(--accent)">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="hidden sm:block text-left min-w-0">
                            <div class="text-xs font-semibold text-gray-800 truncate max-w-[120px] leading-tight">
                                {{ auth()->user()->name }}
                            </div>
                            <div class="text-[10px] text-gray-400 truncate max-w-[120px] leading-tight">
                                {{ auth()->user()->email }}
                            </div>
                        </div>
                        <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="profileOpen" x-transition x-cloak
                        class="absolute top-11 right-0 w-44 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
                        <a href="{{ route('profile') }}"
                            class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Profile
                        </a>
                        <div class="border-t border-gray-100"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Log Out
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <!-- Scrollable content below top bar -->
        <div class="flex-1 overflow-y-auto">

            @if (isset($header))
                <header class="bg-white shadow flex-shrink-0 border-b border-gray-200">
                    <div class="max-w-7xl mx-auto py-5 px-6 sm:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <main>
                {{ $slot }}
            </main>
        </div>
    </div>

    @stack('scripts')

    <!-- ═══ TOAST CONTAINER ════════════════════════════════════════════════ -->
    <div x-data x-cloak class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 items-end pointer-events-none"
        style="min-width:0;max-width:360px">
        <template x-for="t in $store.toasts.items" :key="t.id">
            <div x-show="t.visible" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-x-4 scale-95"
                x-transition:enter-end="opacity-100 translate-x-0 scale-100"
                x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 translate-x-0 scale-100"
                x-transition:leave-end="opacity-0 translate-x-4 scale-95"
                class="pointer-events-auto flex items-start gap-3 rounded-xl shadow-lg border px-4 py-3 text-sm w-80"
                :class="t.wrap">
                <span x-html="t.icon" class="flex-shrink-0 mt-0.5"></span>
                <div class="flex-1 min-w-0">
                    <p x-text="t.title" x-show="t.title" class="font-semibold leading-tight mb-0.5"></p>
                    <p x-text="t.message" class="leading-snug text-gray-600"></p>
                </div>
                <button @click="$store.toasts.dismiss(t.id)"
                    class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition mt-0.5" title="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

    <!-- ═══ ALERT / CONFIRM MODAL ══════════════════════════════════════════ -->
    <div x-data x-cloak x-show="$store.modal.show" class="fixed inset-0 z-[9998] flex items-center justify-center p-4"
        @keydown.escape.window="$store.modal.type !== 'confirm' && $store.modal.answer(false)">

        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-transition:enter="transition duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="$store.modal.type !== 'confirm' && $store.modal.answer(false)">
        </div>

        <!-- Card -->
        <div x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

            <!-- Coloured top stripe -->
            <div class="h-1.5 w-full" :class="{
                         'bg-indigo-500': $store.modal.type === 'confirm',
                         'bg-green-500':  $store.modal.type === 'success',
                         'bg-red-500':    $store.modal.type === 'error',
                         'bg-amber-500':  $store.modal.type === 'warning',
                         'bg-blue-500':   $store.modal.type === 'info',
                     }"></div>

            <div class="p-6">
                <!-- Icon ring -->
                <div class="flex justify-center mb-4">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center" :class="{
                                 'bg-indigo-100': $store.modal.type === 'confirm',
                                 'bg-green-100':  $store.modal.type === 'success',
                                 'bg-red-100':    $store.modal.type === 'error',
                                 'bg-amber-100':  $store.modal.type === 'warning',
                                 'bg-blue-100':   $store.modal.type === 'info',
                             }">
                        <!-- Confirm / question -->
                        <svg x-show="$store.modal.type === 'confirm'" class="w-7 h-7 text-indigo-600" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Success -->
                        <svg x-show="$store.modal.type === 'success'" class="w-7 h-7 text-green-600" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Error -->
                        <svg x-show="$store.modal.type === 'error'" class="w-7 h-7 text-red-600" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Warning -->
                        <svg x-show="$store.modal.type === 'warning'" class="w-7 h-7 text-amber-600" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <!-- Info -->
                        <svg x-show="$store.modal.type === 'info'" class="w-7 h-7 text-blue-600" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Text -->
                <h3 x-text="$store.modal.title" x-show="$store.modal.title"
                    class="text-lg font-bold text-gray-900 text-center mb-2"></h3>
                <p x-text="$store.modal.message" class="text-sm text-gray-600 text-center leading-relaxed"></p>

                <!-- Buttons -->
                <div class="mt-6 flex gap-3"
                    :class="$store.modal.type === 'confirm' ? 'justify-center' : 'justify-center'">

                    <!-- Cancel (confirm only) -->
                    <button x-show="$store.modal.type === 'confirm'" @click="$store.modal.answer(false)"
                        class="flex-1 px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                        <span x-text="$store.modal.cancelLabel"></span>
                    </button>

                    <!-- Primary action -->
                    <button @click="$store.modal.answer(true)"
                        class="flex-1 px-4 py-2.5 rounded-xl text-sm font-bold text-white transition" :class="{
                                    'bg-indigo-600 hover:bg-indigo-700': $store.modal.type === 'confirm',
                                    'bg-green-600 hover:bg-green-700':   $store.modal.type === 'success',
                                    'bg-red-600 hover:bg-red-700':       $store.modal.type === 'error',
                                    'bg-amber-600 hover:bg-amber-700':   $store.modal.type === 'warning',
                                    'bg-blue-600 hover:bg-blue-700':     $store.modal.type === 'info',
                                }">
                        <span x-text="$store.modal.confirmLabel"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ GLOBAL EVENT WIRING ═════════════════════════════════════════════ -->
    <script>
        document.addEventListener('livewire:initialized', () => {

            /* Theme update */
            Livewire.on('theme-updated', ({ bg, header, active, accent }) => {
                const root = document.documentElement;
                root.style.setProperty('--sidebar-bg', bg);
                root.style.setProperty('--sidebar-header-bg', header);
                root.style.setProperty('--sidebar-active-bg', active);
                root.style.setProperty('--accent', accent);
            });

            /* Livewire notify → toast */
            Livewire.on('notify', ({ type, message, title, duration }) => {
                Alpine.store('toasts').add(type || 'info', message, { title, duration });
            });

            /* Livewire alert-modal → modal */
            Livewire.on('alert', ({ type, title, message, confirmLabel }) => {
                Alpine.store('modal').open({
                    type: type || 'info',
                    title: title || '',
                    message: message || '',
                    confirmLabel: confirmLabel || 'OK',
                });
            });

            /* Session flash → toast (fires on page load / after redirect) */
            @if(session('message'))
                Alpine.store('toasts').add('success', @json(session('message')));
            @endif
            @if(session('error'))
                Alpine.store('toasts').add('error', @json(session('error')));
            @endif
            @if(session('warning'))
                Alpine.store('toasts').add('warning', @json(session('warning')));
            @endif
            @if(session('info'))
                Alpine.store('toasts').add('info', @json(session('info')));
            @endif
            });

        /* Global JS helpers */
        window.prmsToast = (type, message, opts) => Alpine.store('toasts').add(type, message, opts);
        window.prmsConfirm = (title, message, opts) => Alpine.store('modal').open({ type: 'confirm', title, message, ...opts });
        window.prmsAlert = (type, title, message, opts) => Alpine.store('modal').open({ type: type || 'info', title, message, confirmLabel: 'OK', ...opts });
    </script>
</body>

</html>