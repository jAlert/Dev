<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Module Management') }}
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-semibold">Custom Modules</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Drag rows to reorder</p>
                    </div>
                    <a href="{{ route('builder.modules.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition" wire:navigate>Create Module</a>
                </div>

                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-3 py-3 w-8"></th>
                            <th class="px-6 py-3 text-left">Name</th>
                            <th class="hidden sm:table-cell px-6 py-3 text-left">Slug</th>
                            <th class="hidden sm:table-cell px-6 py-3 text-left">Fields</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="module-sortable" class="divide-y divide-gray-200">
                        @foreach($modules as $module)
                            <tr data-id="{{ $module->id }}" class="hover:bg-gray-50 transition-colors">
                                <td class="px-3 py-4 text-gray-300 cursor-grab active:cursor-grabbing" title="Drag to reorder">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 6a2 2 0 110-4 2 2 0 010 4zm8 0a2 2 0 110-4 2 2 0 010 4zM8 14a2 2 0 110-4 2 2 0 010 4zm8 0a2 2 0 110-4 2 2 0 010 4zM8 22a2 2 0 110-4 2 2 0 010 4zm8 0a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                </td>
                                <td class="px-6 py-4 font-medium">{{ $module->name }}</td>
                                <td class="hidden sm:table-cell px-6 py-4 text-gray-500">{{ $module->slug }}</td>
                                <td class="hidden sm:table-cell px-6 py-4">{{ $module->fields_count }}</td>
                                <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('builder.modules.edit', $module) }}" class="text-indigo-600 hover:text-indigo-900" wire:navigate>Edit</a>
                                    <a href="{{ route('builder.workflow.stages', $module) }}" class="text-purple-600 hover:text-purple-900" wire:navigate>Stages</a>
                                    <a href="{{ route('builder.workflow.manager', $module) }}" class="text-teal-600 hover:text-teal-900" wire:navigate>Workflows</a>
                                    <button wire:click="delete({{ $module->id }})" wire:confirm="Are you sure you want to delete this module? This will also delete all dynamic records linked to it." class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                @if($modules->isEmpty())
                    <p class="mt-8 text-center text-gray-500 italic">No custom modules created yet. Build your first one!</p>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:navigated', initSortable);
    document.addEventListener('DOMContentLoaded', initSortable);

    function initSortable() {
        const el = document.getElementById('module-sortable');
        if (!el || el._sortable) return;

        el._sortable = new Sortable(el, {
            handle: 'td:first-child',
            animation: 150,
            ghostClass: 'bg-indigo-50',
            onEnd() {
                const order = [...el.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
                @this.reorder(order);
            }
        });
    }
</script>
