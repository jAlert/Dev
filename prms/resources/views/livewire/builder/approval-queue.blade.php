<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Approval Queue
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                @if($pendingRecords->isEmpty())
                    <p class="text-gray-500 italic text-center py-8">No records pending your approval.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="py-3 px-4 text-gray-600 font-semibold">Title</th>
                                    <th class="py-3 px-4 text-gray-600 font-semibold">Stage</th>
                                    <th class="hidden sm:table-cell py-3 px-4 text-gray-600 font-semibold">Status</th>
                                    <th class="hidden md:table-cell py-3 px-4 text-gray-600 font-semibold">Submitted By</th>
                                    <th class="hidden md:table-cell py-3 px-4 text-gray-600 font-semibold">Date</th>
                                    <th class="py-3 px-4 text-gray-600 font-semibold">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingRecords as $rec)
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 px-4 font-medium">
                                            {{ $rec->data['title'] ?? $rec->data['name'] ?? $rec->data['subject'] ?? '#' . $rec->id }}
                                        </td>
                                        <td class="py-3 px-4">
                                            {{ $rec->currentStage?->name ?? '—' }}
                                            @if($rec->currentStage)
                                                <span
                                                    class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase
                                                                                                                                                                                                                                                                                                                                                                        {{ $rec->currentStage->stage_type === 'review' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                                                    {{ $rec->currentStage->stage_type === 'review' ? 'Review' : 'Approval' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="hidden sm:table-cell py-3 px-4">
                                            <span
                                                class="px-3 py-1 rounded-full text-xs font-bold
                                                                                                                                                                                                                                                            {{ $rec->status === 'Submitted' ? 'bg-blue-100 text-blue-800' : '' }}
                                                                                                                                                                                                                                                            {{ $rec->status === 'Under Review' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                                                                                                                                                                                                                                            {{ !in_array($rec->status, ['Submitted', 'Under Review']) ? 'bg-gray-100 text-gray-700' : '' }}
                                                                                                                                                                                                                                                        ">{{ $rec->status }}</span>
                                        </td>
                                        <td class="hidden md:table-cell py-3 px-4 text-gray-600">
                                            {{ $rec->creator?->name ?? '—' }}
                                        </td>
                                        <td class="hidden md:table-cell py-3 px-4 text-gray-500 text-xs">
                                            {{ $rec->updated_at->diffForHumans() }}
                                        </td>
                                        <td class="py-3 px-4">
                                            @if($rec->module)
                                                <a href="{{ route('dynamic.show', ['moduleSlug' => $rec->module->slug, 'record' => $rec->id]) }}"
                                                    wire:navigate class="text-indigo-600 hover:underline font-medium text-xs">
                                                    Review →
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $pendingRecords->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>