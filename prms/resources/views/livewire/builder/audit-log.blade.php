<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit Log</h2>
</x-slot>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Filters --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Module</label>
                    <select wire:model.live="moduleFilter" class="block w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Modules</option>
                        @foreach($modules as $m)
                            <option value="{{ $m->slug }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Action</label>
                    <select wire:model.live="actionFilter" class="block w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Actions</option>
                        @foreach($actions as $a)
                            <option value="{{ $a }}">{{ ucfirst($a) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">User</label>
                    <select wire:model.live="userFilter" class="block w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">From</label>
                    <input type="date" wire:model.live="dateFrom" class="block w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">To</label>
                    <input type="date" wire:model.live="dateTo" class="block w-full rounded border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="flex items-end">
                    <button wire:click="clearFilters" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium border border-indigo-300 rounded px-3 py-2 hover:bg-indigo-50 w-full">
                        Clear
                    </button>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold border-b">
                        <tr>
                            <th class="px-4 py-3 text-left">Action</th>
                            <th class="px-4 py-3 text-left">User</th>
                            <th class="px-4 py-3 text-left">Module</th>
                            <th class="px-4 py-3 text-left">Record</th>
                            <th class="px-4 py-3 text-left">Details</th>
                            <th class="px-4 py-3 text-left">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs font-bold uppercase
                                    {{ $log->action === 'created'   ? 'bg-green-100 text-green-700'   : '' }}
                                    {{ $log->action === 'updated'   ? 'bg-blue-100 text-blue-700'     : '' }}
                                    {{ $log->action === 'submitted' ? 'bg-indigo-100 text-indigo-700' : '' }}
                                    {{ $log->action === 'approved'  ? 'bg-green-100 text-green-800'   : '' }}
                                    {{ $log->action === 'returned'  ? 'bg-orange-100 text-orange-700' : '' }}
                                ">{{ $log->action }}</span>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $log->record?->module?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($log->record && $log->record->module)
                                    <a href="{{ route('dynamic.show', ['moduleSlug' => $log->record->module->slug, 'record' => $log->record_id]) }}"
                                       wire:navigate class="text-indigo-600 hover:underline text-xs font-medium">
                                        #{{ $log->record_id }}
                                    </a>
                                @else
                                    <span class="text-gray-400 text-xs">#{{ $log->record_id }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                @if(!empty($log->changes_json['comment']))
                                    <span class="italic">"{{ Str::limit($log->changes_json['comment'], 60) }}"</span>
                                @elseif(!empty($log->changes_json))
                                    <span class="text-gray-400">Data changed</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400">
                                <span title="{{ $log->created_at->format('Y-m-d H:i:s') }}">{{ $log->created_at->diffForHumans() }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400 italic">No audit records found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())
                <div class="px-4 py-4 border-t bg-gray-50">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>

    </div>
</div>
