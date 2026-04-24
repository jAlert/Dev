<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">API Manager</h2>
</x-slot>

<div class="py-8" x-data="{ copied: false }">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- New Token Alert --}}
        @if($newPlainToken)
        <div class="bg-green-50 border border-green-300 rounded-xl p-5 shadow-sm">
            <div class="flex items-start justify-between mb-2">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm font-bold text-green-800">Token created — copy it now, it won't be shown again.</span>
                </div>
                <button wire:click="dismissToken" class="text-green-500 hover:text-green-700 ml-4 flex-shrink-0" title="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex items-center gap-2 mt-3">
                <code class="flex-1 bg-white border border-green-200 rounded-lg px-4 py-2.5 text-sm font-mono text-gray-800 break-all select-all">{{ $newPlainToken }}</code>
                <button @click="navigator.clipboard.writeText('{{ $newPlainToken }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="flex-shrink-0 flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition">
                    <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <svg x-show="copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                </button>
            </div>
        </div>
        @endif

        {{-- Tabs --}}
        <div class="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
            <button wire:click="$set('activeTab', 'tokens')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition {{ $activeTab === 'tokens' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
                API Tokens
            </button>
            <button wire:click="$set('activeTab', 'reference')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition {{ $activeTab === 'reference' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
                API Reference
            </button>
        </div>

        {{-- ── TOKENS TAB ── --}}
        @if($activeTab === 'tokens')
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            {{-- Create Token Form --}}
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">Create New Token</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Token Name</label>
                        <input wire:model="tokenName" type="text" placeholder="e.g. My App Integration"
                               class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        @error('tokenName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Expiry</label>
                        <select wire:model="tokenExpiry" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Never expires</option>
                            <option value="1">1 day</option>
                            <option value="7">7 days</option>
                            <option value="30">30 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-2">Permissions</label>
                        <label class="flex items-center gap-2 cursor-pointer mb-3">
                            <input wire:model.live="fullAccess" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700 font-medium">Full Access (all modules)</span>
                        </label>

                        @if(!$fullAccess)
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-gray-500 font-semibold">Module</th>
                                        <th class="px-3 py-2 text-center text-gray-500 font-semibold">Read</th>
                                        <th class="px-3 py-2 text-center text-gray-500 font-semibold">Write</th>
                                        <th class="px-3 py-2 text-center text-gray-500 font-semibold">Delete</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($modules as $m)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 font-medium text-gray-700">{{ $m->name }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="checkbox" wire:model="abilities" value="{{ $m->slug }}:read" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="checkbox" wire:model="abilities" value="{{ $m->slug }}:write" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="checkbox" wire:model="abilities" value="{{ $m->slug }}:delete" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        </td>
                                    </tr>
                                    @endforeach
                                    @if($modules->isEmpty())
                                    <tr><td colspan="4" class="px-3 py-4 text-center text-gray-400 italic">No modules available</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>

                    <button wire:click="createToken"
                            class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition">
                        Generate Token
                    </button>
                </div>
            </div>

            {{-- Existing Tokens --}}
            <div class="lg:col-span-3 bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide flex-shrink-0">Active Tokens</h3>
                        <span class="text-xs text-gray-400">
                            {{ $filterModule ? $tokens->count() . ' of ' . $allTokens->count() : $allTokens->count() }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($allTokens->isNotEmpty())
                        <button wire:click="revokeAll" wire:confirm="Revoke ALL tokens? This cannot be undone."
                                class="text-xs text-red-600 hover:text-red-800 font-medium hover:underline">
                            Revoke All
                        </button>
                        @endif
                    </div>
                </div>
                <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-2">
                    <input wire:model.live="filterModule" type="text" placeholder="Filter by module slug (e.g. draft_policy)"
                           class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5" />
                    @if($filterModule && $tokens->isNotEmpty())
                    <button wire:click="revokeFiltered"
                            wire:confirm="Revoke all {{ $tokens->count() }} token(s) matching '{{ $filterModule }}'?"
                            class="flex-shrink-0 text-xs text-red-600 hover:text-red-800 font-semibold border border-red-300 hover:border-red-500 px-3 py-1.5 rounded-lg transition whitespace-nowrap">
                        Revoke {{ $tokens->count() }}
                    </button>
                    @endif
                    @if($filterModule)
                    <button wire:click="$set('filterModule', '')" class="text-gray-400 hover:text-gray-600 flex-shrink-0" title="Clear filter">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    @endif
                </div>

                @if($tokens->isEmpty())
                <div class="px-6 py-12 text-center text-gray-400 italic text-sm">No tokens yet.</div>
                @else
                <div class="divide-y divide-gray-100">
                    @foreach($tokens as $token)
                    <div class="px-6 py-4 flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-sm text-gray-800">{{ $token->name }}</span>
                                @if($token->expires_at && $token->expires_at->isPast())
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">Expired</span>
                                @elseif($token->expires_at)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-yellow-100 text-yellow-700">Expires {{ $token->expires_at->diffForHumans() }}</span>
                                @else
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700">No expiry</span>
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach($token->abilities as $ab)
                                    <span class="px-1.5 py-0.5 bg-indigo-50 text-indigo-700 text-[10px] font-medium rounded">{{ $ab }}</span>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-400 mt-1">
                                Created {{ $token->created_at->diffForHumans() }}
                                @if($token->last_used_at) · Last used {{ $token->last_used_at->diffForHumans() }} @endif
                            </p>
                        </div>
                        <button wire:click="revokeToken({{ $token->id }})" wire:confirm="Revoke this token?"
                                class="flex-shrink-0 text-xs text-red-500 hover:text-red-700 font-medium border border-red-200 hover:border-red-400 px-2.5 py-1 rounded-lg transition">
                            Revoke
                        </button>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── API REFERENCE TAB ── --}}
        @if($activeTab === 'reference')
        <div class="space-y-6" x-data="{ curlCopied: null }">

            {{-- Auth Info --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-4">Authentication</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1.5">Base URL</p>
                        <code class="block bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-indigo-700 font-mono text-xs break-all">{{ $baseUrl }}</code>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1.5">Authorization Header</p>
                        <code class="block bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-indigo-700 font-mono text-xs">Authorization: Bearer &lt;your-token&gt;</code>
                    </div>
                </div>
                <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800">
                    <strong>Note:</strong> All API requests require a valid Bearer token in the <code class="font-mono bg-amber-100 px-1 rounded">Authorization</code> header. Tokens are created in the <strong>API Tokens</strong> tab.
                </div>
            </div>

            {{-- Per-Module Endpoints --}}
            @foreach($modules as $mod)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ $mod->name }}</h3>
                        <code class="text-xs text-gray-500 font-mono">/api/dynamic/{{ $mod->slug }}</code>
                    </div>
                    <div class="flex gap-1.5">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-700">{{ $mod->slug }}:read</span>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-green-100 text-green-700">{{ $mod->slug }}:write</span>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-red-100 text-red-700">{{ $mod->slug }}:delete</span>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @php
                    $endpoints = [
                        ['method' => 'GET',    'path' => "/api/dynamic/{$mod->slug}",           'ability' => "{$mod->slug}:read",   'desc' => 'List records. Supports: status, search, date_from, date_to, sort_by, sort_dir, per_page'],
                        ['method' => 'POST',   'path' => "/api/dynamic/{$mod->slug}",           'ability' => "{$mod->slug}:write",  'desc' => 'Create a new record'],
                        ['method' => 'GET',    'path' => "/api/dynamic/{$mod->slug}/{id}",      'ability' => "{$mod->slug}:read",   'desc' => 'Get a single record by ID'],
                        ['method' => 'PUT',    'path' => "/api/dynamic/{$mod->slug}/{id}",      'ability' => "{$mod->slug}:write",  'desc' => 'Update a record by ID'],
                        ['method' => 'DELETE', 'path' => "/api/dynamic/{$mod->slug}/{id}",      'ability' => "{$mod->slug}:delete", 'desc' => 'Delete a record by ID'],
                    ];
                    $methodColors = [
                        'GET'    => 'bg-blue-100 text-blue-700',
                        'POST'   => 'bg-green-100 text-green-700',
                        'PUT'    => 'bg-yellow-100 text-yellow-700',
                        'DELETE' => 'bg-red-100 text-red-700',
                    ];
                    @endphp

                    @foreach($endpoints as $ep)
                    <div x-data="{ open: false }" class="px-6 py-3">
                        <div class="flex items-center gap-3 cursor-pointer" @click="open = !open">
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold font-mono w-16 text-center {{ $methodColors[$ep['method']] }}">{{ $ep['method'] }}</span>
                            <code class="text-sm font-mono text-gray-700 flex-1">{{ $ep['path'] }}</code>
                            <span class="text-xs text-gray-400 hidden sm:block flex-1">{{ $ep['desc'] }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">{{ $ep['ability'] }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>

                        <div x-show="open" x-transition class="mt-3 ml-20" style="display:none;">
                            <p class="text-xs text-gray-500 mb-2 sm:hidden">{{ $ep['desc'] }}</p>
                            @php
                                $appUrl = rtrim(config('app.url'), '/');
                                $curl = match($ep['method']) {
                                    'GET'    => "curl -H \"Authorization: Bearer <token>\" \\\n  {$appUrl}{$ep['path']}",
                                    'POST'   => "curl -X POST -H \"Authorization: Bearer <token>\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"data\": {}}' \\\n  {$appUrl}{$ep['path']}",
                                    'PUT'    => "curl -X PUT -H \"Authorization: Bearer <token>\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"data\": {}}' \\\n  {$appUrl}{$ep['path']}",
                                    'DELETE' => "curl -X DELETE -H \"Authorization: Bearer <token>\" \\\n  {$appUrl}{$ep['path']}",
                                    default  => '',
                                };
                            @endphp
                            <div class="relative">
                                <pre class="bg-gray-900 text-green-300 text-xs rounded-lg px-4 py-3 overflow-x-auto font-mono leading-relaxed">{{ $curl }}</pre>
                                <button @click="navigator.clipboard.writeText(`{{ str_replace('`', '\`', $curl) }}`); curlCopied = '{{ $ep['method'] }}-{{ $mod->slug }}'; setTimeout(() => curlCopied = null, 2000)"
                                        class="absolute top-2 right-2 px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-300 text-[10px] rounded transition">
                                    <span x-show="curlCopied !== '{{ $ep['method'] }}-{{ $mod->slug }}'">Copy</span>
                                    <span x-show="curlCopied === '{{ $ep['method'] }}-{{ $mod->slug }}'">Copied!</span>
                                </button>
                            </div>

                            {{-- Field reference for POST/PUT --}}
                            @if(in_array($ep['method'], ['POST', 'PUT']) && $mod->fields->isNotEmpty())
                            <div class="mt-3 border border-gray-200 rounded-lg overflow-hidden">
                                <div class="px-3 py-2 bg-gray-50 text-xs font-semibold text-gray-600 uppercase">Fields (data object)</div>
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 border-t border-gray-200">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-gray-500 font-semibold">Field</th>
                                            <th class="px-3 py-2 text-left text-gray-500 font-semibold">Type</th>
                                            <th class="px-3 py-2 text-left text-gray-500 font-semibold">Required</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($mod->fields as $field)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-indigo-700">{{ $field->slug }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ $field->type }}</td>
                                            <td class="px-3 py-2">
                                                @if($field->is_required)
                                                    <span class="text-red-600 font-semibold">Yes</span>
                                                @else
                                                    <span class="text-gray-400">No</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach

            @if($modules->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-400 italic text-sm">
                No modules configured yet.
            </div>
            @endif
        </div>
        @endif

    </div>
</div>
