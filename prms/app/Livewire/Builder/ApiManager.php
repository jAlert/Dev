<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use App\Models\Module;
use Livewire\Attributes\Layout;

class ApiManager extends Component
{
    public string  $tokenName      = '';
    public string  $tokenExpiry    = '';
    public bool    $fullAccess     = true;
    public array   $abilities      = [];   // e.g. ['orders:read', 'orders:write']
    public ?string $newPlainToken  = null;
    public string  $activeTab      = 'tokens';
    public string  $filterModule   = '';

    public function createToken(): void
    {
        $this->validate(['tokenName' => 'required|string|max:100']);

        $selectedAbilities = $this->fullAccess ? ['*'] : array_values(array_filter($this->abilities));

        if (empty($selectedAbilities)) {
            $this->addError('tokenName', 'Select at least one permission.');
            return;
        }

        $expiresAt = match($this->tokenExpiry) {
            '1'   => now()->addDay(),
            '7'   => now()->addDays(7),
            '30'  => now()->addDays(30),
            '365' => now()->addYear(),
            default => null,
        };

        $token = auth()->user()->createToken($this->tokenName, $selectedAbilities, $expiresAt);

        $this->newPlainToken = $token->plainTextToken;
        $this->tokenName     = '';
        $this->tokenExpiry   = '';
        $this->fullAccess    = true;
        $this->abilities     = [];
    }

    public function revokeToken(int $id): void
    {
        auth()->user()->tokens()->where('id', $id)->delete();
    }

    public function revokeAll(): void
    {
        auth()->user()->tokens()->delete();
        $this->newPlainToken = null;
    }

    public function revokeFiltered(): void
    {
        $slug = trim($this->filterModule);
        if ($slug === '') return;

        auth()->user()->tokens()->get()
            ->filter(fn($token) => $this->tokenMatchesFilter($token, $slug))
            ->each->delete();
    }

    private function tokenMatchesFilter($token, string $slug): bool
    {
        return collect($token->abilities)->contains(
            fn($a) => str_starts_with(strtolower($a), strtolower($slug) . ':')
                   || strtolower($a) === strtolower($slug)
        );
    }

    public function dismissToken(): void
    {
        $this->newPlainToken = null;
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $allTokens = auth()->user()->tokens()->latest()->get();
        $slug      = trim($this->filterModule);
        $tokens    = $slug
            ? $allTokens->filter(fn($t) => $this->tokenMatchesFilter($t, $slug))->values()
            : $allTokens;
        $modules   = Module::whereNull('source_module_id')->orderBy('sort_order')->get();
        $baseUrl   = rtrim(config('app.url'), '/') . '/api';

        return view('livewire.builder.api-manager', compact('tokens', 'allTokens', 'modules', 'baseUrl'));
    }
}
