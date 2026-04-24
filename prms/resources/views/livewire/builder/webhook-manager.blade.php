<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Webhook Manager</h2>
</x-slot>

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Form --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-bold mb-5">{{ $editingId ? 'Edit Webhook' : 'New Webhook' }}</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" wire:model="name" placeholder="e.g. Notify Zapier on Approval"
                            class="block w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                        <input type="url" wire:model="url" placeholder="https://hooks.zapier.com/..."
                            class="block w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('url') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Module (optional)</label>
                        <select wire:model="module_id" class="block w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Modules</option>
                            @foreach($modules as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Events</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($availableEvents as $ev)
                                <label class="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="events" value="{{ $ev }}"
                                        class="rounded border-gray-300 text-indigo-600">
                                    {{ ucfirst($ev) }}
                                </label>
                            @endforeach
                        </div>
                        @error('events') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Secret (HMAC Signing)</label>
                        <input type="text" wire:model="secret" placeholder="Optional secret key"
                            class="block w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">
                        <p class="text-xs text-gray-400 mt-1">Sent as X-PRMS-Signature header (SHA-256 HMAC).</p>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600">
                        <span class="text-sm font-medium text-gray-700">Active</span>
                    </label>
                </div>

                <div class="flex gap-3 mt-5 pt-4 border-t">
                    <button wire:click="save" class="bg-indigo-600 text-white px-5 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm">Save</button>
                    @if($editingId)
                        <button wire:click="createNew" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-300">Cancel</button>
                    @endif
                </div>
            </div>

            {{-- Webhook List --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-bold mb-4">Configured Webhooks</h3>

                @if($webhooks->isEmpty())
                    <p class="text-gray-500 italic text-sm">No webhooks configured yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach($webhooks as $wh)
                        <div class="border rounded-lg p-3 {{ $wh->is_active ? 'bg-white' : 'bg-gray-50 opacity-60' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-sm text-gray-800">{{ $wh->name }}</span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold {{ $wh->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">
                                            {{ $wh->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $wh->url }}</p>
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        @foreach($wh->events as $ev)
                                            <span class="text-[10px] px-1.5 py-0.5 bg-indigo-100 text-indigo-700 rounded font-medium">{{ $ev }}</span>
                                        @endforeach
                                    </div>
                                    @if($wh->module)
                                        <p class="text-xs text-gray-400 mt-1">Module: {{ $wh->module->name }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-col gap-1.5 flex-shrink-0 text-right">
                                    <button wire:click="edit({{ $wh->id }})" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">Edit</button>
                                    <button wire:click="toggleActive({{ $wh->id }})" class="text-gray-500 hover:text-gray-700 text-xs font-medium">
                                        {{ $wh->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                    <button wire:click="viewLogs({{ $wh->id }})" class="text-blue-500 hover:text-blue-700 text-xs font-medium">Logs</button>
                                    <button wire:click="delete({{ $wh->id }})" wire:confirm="Delete this webhook?" class="text-red-500 hover:text-red-700 text-xs font-medium">Delete</button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Delivery Logs --}}
        @if($viewingLogsFor)
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold">Delivery Logs</h3>
                <button wire:click="$set('viewingLogsFor', null)" class="text-xs text-gray-500 hover:text-gray-700">Close ✕</button>
            </div>
            @if($logs->isEmpty())
                <p class="text-sm text-gray-500 italic">No deliveries yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="text-gray-500 uppercase border-b">
                            <tr>
                                <th class="py-2 px-3 text-left">Event</th>
                                <th class="py-2 px-3 text-left">Status</th>
                                <th class="py-2 px-3 text-left">Response</th>
                                <th class="py-2 px-3 text-left">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($logs as $log)
                            <tr class="{{ $log->success ? '' : 'bg-red-50' }}">
                                <td class="py-2 px-3 font-medium">{{ $log->event }}</td>
                                <td class="py-2 px-3">
                                    @if($log->success)
                                        <span class="text-green-600 font-semibold">✓ {{ $log->response_code }}</span>
                                    @else
                                        <span class="text-red-600 font-semibold">✗ {{ $log->response_code ?? 'Error' }}</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-gray-500 max-w-xs truncate">{{ $log->response_body }}</td>
                                <td class="py-2 px-3 text-gray-400">{{ $log->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif

        <div class="text-right">
            <a href="{{ route('builder.modules.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Back to Modules</a>
        </div>
    </div>
</div>
