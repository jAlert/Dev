<x-slot name="header">
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $module->name }}
        </h2>
        <div class="flex gap-2">
            <a href="{{ route('dynamic.export-csv', $moduleSlug) }}" class="bg-white border border-gray-300 text-gray-700 px-3 py-2 rounded shadow-sm text-sm font-medium hover:bg-gray-50">
                Export CSV
            </a>
            @if(auth()->user()->can("create-{$moduleSlug}"))
                <a href="{{ route('dynamic.create', $moduleSlug) }}" wire:navigate class="bg-indigo-600 text-white px-4 py-2 rounded shadow-sm text-sm font-bold hover:bg-indigo-700">Create New</a>
            @endif
        </div>
    </div>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        {{-- Filter Bar --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-3">
                {{-- Search --}}
                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Search</label>
                    <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search all fields..." class="block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                {{-- Status filter --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Status</label>
                    <select wire:model.live="statusFilter" class="block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">All statuses</option>
                        @foreach($allStatuses as $st)
                            <option value="{{ $st }}">{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Stage filter --}}
                @if($stages->isNotEmpty())
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Approval Stage</label>
                    <select wire:model.live="stageFilter" class="block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">All stages</option>
                        <option value="none">No stage (not submitted)</option>
                        @foreach($stages as $stage)
                            <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                {{-- Date From --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Created From</label>
                    <input type="date" wire:model.live="dateFrom" class="block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                {{-- Date To --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Created To</label>
                    <input type="date" wire:model.live="dateTo" class="block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                {{-- Clear button --}}
                <div class="flex items-end">
                    <button wire:click="clearFilters" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium border border-indigo-300 rounded px-3 py-2 hover:bg-indigo-50">Clear Filters</button>
                </div>
            </div>
        </div>

        {{-- Records Table --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 overflow-x-auto">
                <table class="min-w-full text-left border-collapse">
                    @php $indexFields = $module->fields->where('show_in_index', true); @endphp
                    <thead>
                        <tr>
                            @foreach($indexFields as $field)
                                <th class="border-b p-3 text-sm font-semibold text-gray-600">{{ $field->name }}</th>
                            @endforeach
                            <th class="border-b p-3 text-sm font-semibold text-gray-600 cursor-pointer hover:text-indigo-600" wire:click="sortByColumn('status')">
                                Status @if($sortBy === 'status') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif
                            </th>
                            @if($stages->isNotEmpty())
                                <th class="border-b p-3 text-sm font-semibold text-gray-600">Stage</th>
                            @endif
                            <th class="hidden md:table-cell border-b p-3 text-sm font-semibold text-gray-600 cursor-pointer hover:text-indigo-600" wire:click="sortByColumn('created_at')">
                                Created @if($sortBy === 'created_at') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif
                            </th>
                            <th class="border-b p-3 text-sm font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($records as $rec)
                            <tr class="hover:bg-gray-50 border-b">
                                @foreach($indexFields as $field)
                                    <td class="p-3 text-sm text-gray-800">
                                        @php $val = $rec->data[$field->slug] ?? null; @endphp
                                        @if($field->type === 'boolean')
                                            {{ $val ? 'Yes' : 'No' }}
                                        @elseif($field->type === 'attachment' && is_array($val) && !empty($val))
                                            @php $latest = $val[0]; @endphp
                                            <a href="{{ Storage::url($latest['path']) }}" target="_blank" class="text-indigo-600 hover:underline border border-indigo-200 bg-indigo-50 px-2 py-1 rounded text-xs whitespace-nowrap">
                                                View File <span class="text-indigo-400">(v{{ count($val) }})</span>
                                            </a>
                                        @elseif($field->type === 'attachment' && !empty($val))
                                            <a href="{{ Storage::url($val) }}" target="_blank" class="text-indigo-600 hover:underline border border-indigo-200 bg-indigo-50 px-2 py-1 rounded text-xs whitespace-nowrap">View File</a>
                                        @elseif($field->type === 'multi_select' && is_array($val))
                                            {{ implode(', ', $val) ?: '-' }}
                                        @elseif($field->type === 'user' && $val)
                                            {{ $usersMap[$val] ?? 'User #'.$val }}
                                        @elseif($field->type === 'currency' && $val !== null && $val !== '')
                                            ${{ number_format((float)$val, 2) }}
                                        @elseif($field->type === 'url' && !empty($val))
                                            <a href="{{ $val }}" target="_blank" class="text-indigo-600 hover:underline text-xs truncate max-w-xs block">{{ $val }}</a>
                                        @elseif($field->type === 'text_editor' && !empty($val))
                                            <span class="text-gray-500 truncate block max-w-xs">{{ Str::limit(strip_tags($val), 80) }}</span>
                                        @else
                                            {{ $val ?? '-' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="p-3 text-sm">
                                    @if(auth()->user()->can("change-status-{$moduleSlug}"))
                                        <select wire:change="updateStatus({{ $rec->id }}, $event.target.value)" class="block w-full rounded border-gray-300 text-xs font-medium py-1 px-2 bg-gray-50 focus:ring-indigo-500">
                                            @foreach($allStatuses as $st)
                                                <option value="{{ $st }}" {{ $rec->status === $st ? 'selected' : '' }}>{{ $st }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs font-bold whitespace-nowrap
                                            {{ $rec->status === 'Draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $rec->status === 'Submitted' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $rec->status === 'Under Review' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                            {{ $rec->status === 'Returned' ? 'bg-orange-100 text-orange-800' : '' }}
                                            {{ $rec->status === 'Completed' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $rec->status === 'Archived' ? 'bg-gray-200 text-gray-600' : '' }}
                                        ">{{ $rec->status }}</span>
                                    @endif
                                </td>
                                @if($stages->isNotEmpty())
                                    <td class="p-3 text-xs text-gray-500">{{ $rec->currentStage?->name ?? '—' }}</td>
                                @endif
                                <td class="hidden md:table-cell p-3 text-xs text-gray-400">{{ $rec->created_at->format('M d, Y') }}</td>
                                <td class="p-3 text-sm">
                                    <div class="flex gap-3">
                                        <a href="{{ route('dynamic.show', ['moduleSlug' => $moduleSlug, 'record' => $rec->id]) }}" wire:navigate class="hover:underline text-gray-600 font-medium whitespace-nowrap text-xs">View</a>
                                        @if($canEditRecords && $rec->status !== 'Completed' && ($rec->currentStage === null || ($rec->currentStage->allow_edit ?? true)))
                                            <a href="{{ route('dynamic.edit', ['moduleSlug' => $moduleSlug, 'record' => $rec->id]) }}" wire:navigate class="hover:underline text-indigo-600 font-medium whitespace-nowrap text-xs">Edit</a>
                                        @endif
                                        @if(auth()->user()->can("delete-{$moduleSlug}"))
                                            <button wire:click="deleteRecord({{ $rec->id }})" wire:confirm="Are you sure you want to delete this record?" class="text-red-500 hover:underline whitespace-nowrap text-xs">Delete</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @if($records->isEmpty())
                            <tr><td colspan="10" class="p-6 text-center text-gray-500 italic">No records found.</td></tr>
                        @endif
                    </tbody>
                </table>
                <div class="mt-4">
                    {{ $records->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
