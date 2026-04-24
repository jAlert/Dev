<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $theme = 'indigo';

    public function mount(): void
    {
        $this->theme = Auth::user()->theme ?? 'indigo';
    }

    public function setTheme(string $theme): void
    {
        $themes = [
            'indigo' => ['bg' => '#1e1e2f', 'header' => '#161624', 'active' => '#2d2d44', 'accent' => '#6366f1'],
            'blue'   => ['bg' => '#0d1b2e', 'header' => '#081221', 'active' => '#163354', 'accent' => '#3b82f6'],
            'green'  => ['bg' => '#0f1f18', 'header' => '#081510', 'active' => '#163828', 'accent' => '#22c55e'],
            'rose'   => ['bg' => '#2a0f16', 'header' => '#1e0a0d', 'active' => '#4a1020', 'accent' => '#f43f5e'],
            'amber'  => ['bg' => '#1f1a0d', 'header' => '#141005', 'active' => '#3d3010', 'accent' => '#f59e0b'],
            'slate'  => ['bg' => '#1a1f2e', 'header' => '#13172a', 'active' => '#2d3548', 'accent' => '#64748b'],
        ];

        if (!array_key_exists($theme, $themes)) return;

        $this->theme = $theme;
        Auth::user()->update(['theme' => $theme]);

        $t = $themes[$theme];
        $this->dispatch('theme-updated',
            bg: $t['bg'], header: $t['header'], active: $t['active'], accent: $t['accent']
        );
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Color Theme</h2>
        <p class="mt-1 text-sm text-gray-600">Choose a sidebar and accent color for your interface.</p>
    </header>

    <div class="mt-6">
        <div class="flex flex-wrap gap-4">
            @php
            $themes = [
                'indigo' => ['label' => 'Indigo',  'sidebar' => '#1e1e2f', 'accent' => '#6366f1'],
                'blue'   => ['label' => 'Blue',    'sidebar' => '#0d1b2e', 'accent' => '#3b82f6'],
                'green'  => ['label' => 'Green',   'sidebar' => '#0f1f18', 'accent' => '#22c55e'],
                'rose'   => ['label' => 'Rose',    'sidebar' => '#2a0f16', 'accent' => '#f43f5e'],
                'amber'  => ['label' => 'Amber',   'sidebar' => '#1f1a0d', 'accent' => '#f59e0b'],
                'slate'  => ['label' => 'Slate',   'sidebar' => '#1a1f2e', 'accent' => '#64748b'],
            ];
            @endphp

            @foreach($themes as $key => $t)
            <button wire:click="setTheme('{{ $key }}')"
                    title="{{ $t['label'] }}"
                    class="flex flex-col items-center gap-2 group">
                {{-- Mini sidebar preview --}}
                <div class="w-14 h-16 rounded-lg overflow-hidden shadow-md border-2 transition-all
                    {{ $theme === $key ? 'border-gray-800 scale-105' : 'border-transparent group-hover:border-gray-400' }}">
                    <div class="w-full h-4" style="background-color: {{ $t['sidebar'] }}"></div>
                    <div class="flex h-12">
                        <div class="w-4 flex flex-col gap-1 p-1" style="background-color: {{ $t['sidebar'] }}">
                            <div class="h-1 rounded-full opacity-50 bg-white"></div>
                            <div class="h-1 rounded-full" style="background-color: {{ $t['accent'] }}"></div>
                            <div class="h-1 rounded-full opacity-50 bg-white"></div>
                            <div class="h-1 rounded-full opacity-50 bg-white"></div>
                        </div>
                        <div class="flex-1 bg-gray-100 flex items-center justify-center">
                            <div class="w-4 h-4 rounded" style="background-color: {{ $t['accent'] }}"></div>
                        </div>
                    </div>
                </div>
                <span class="text-xs font-medium {{ $theme === $key ? 'text-gray-900' : 'text-gray-500' }}">
                    {{ $t['label'] }}
                    @if($theme === $key)
                        <span class="ml-1">✓</span>
                    @endif
                </span>
            </button>
            @endforeach
        </div>

        <x-action-message class="mt-3" on="theme-updated">
            Theme saved.
        </x-action-message>
    </div>
</section>
