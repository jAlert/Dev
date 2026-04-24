<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Roles & Permissions Management') }}
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col md:flex-row gap-6">
        
        <!-- Role List -->
        <div class="w-full md:w-1/3 bg-white overflow-hidden shadow-sm sm:rounded-lg flex flex-col">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-700">Roles</h3>
                <button wire:click="createNew" class="text-sm bg-indigo-600 text-white px-3 py-1 rounded shadow-sm hover:bg-indigo-700">+ New Role</button>
            </div>
            <div class="overflow-y-auto flex-1 p-2" style="max-height: 600px;">
                @foreach($roles as $r)
                    <div wire:click="selectRole({{ $r->id }})" class="p-3 mb-2 rounded cursor-pointer border hover:border-indigo-500 transition-colors {{ optional($selectedRole)->id === $r->id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' }} flex justify-between items-center">
                        <div>
                            <div class="font-medium text-gray-900">{{ $r->name }}</div>
                            <div class="text-xs text-gray-500">{{ $r->permissions->count() }} permissions</div>
                        </div>
                        <button wire:click.stop="deleteRole({{ $r->id }})" wire:confirm="Are you sure you want to delete this role?" class="text-red-500 hover:text-red-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                @endforeach
                @if($roles->isEmpty())
                    <div class="p-4 text-sm text-gray-500 italic text-center">No custom roles built yet.</div>
                @endif
            </div>
        </div>

        <!-- Form & Permissions -->
        <div class="w-full md:w-2/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-bold border-b pb-2 mb-4">{{ $selectedRole ? 'Edit Role: '.$selectedRole->name : 'Create New Role' }}</h3>
            
            <form wire:submit="saveRole">
                <div class="mb-6">
                    <label class="block text-xs font-semibold text-gray-600 uppercase">Role Name</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded border-gray-300 text-sm" placeholder="e.g. Sales, Manager, Reviewer">
                    @error('name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <h4 class="font-bold text-gray-700 bg-gray-100 p-2 rounded mb-4">Module Access Levels for this Role</h4>
                <div class="space-y-4">
                    @foreach($modules as $m)
                        <div class="border rounded p-4">
                            <h5 class="font-semibold text-indigo-700 mb-2 border-b pb-1">{{ $m->name }}</h5>
                            <div class="flex gap-4">
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="rolePermissions.view-{{ $m->slug }}" class="rounded text-indigo-600 mr-2 border-gray-300 shadow-sm focus:ring-indigo-500"> View
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="rolePermissions.create-{{ $m->slug }}" class="rounded text-green-600 mr-2 border-gray-300 shadow-sm focus:ring-green-500"> Create
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="rolePermissions.edit-{{ $m->slug }}" class="rounded text-yellow-600 mr-2 border-gray-300 shadow-sm focus:ring-yellow-500"> Edit
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="rolePermissions.delete-{{ $m->slug }}" class="rounded text-red-600 mr-2 border-gray-300 shadow-sm focus:ring-red-500"> Delete
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="rolePermissions.change-status-{{ $m->slug }}" class="rounded text-purple-600 mr-2 border-gray-300 shadow-sm focus:ring-purple-500"> Change Status
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="rolePermissions.review-{{ $m->slug }}" class="rounded text-teal-600 mr-2 border-gray-300 shadow-sm focus:ring-teal-500"> Review
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="rolePermissions.approve-{{ $m->slug }}" class="rounded text-blue-600 mr-2 border-gray-300 shadow-sm focus:ring-blue-500"> Approve
                                </label>
                            </div>
                        </div>
                    @endforeach
                    @if($modules->isEmpty())
                        <p class="text-sm text-gray-500 italic">No modules built yet.</p>
                    @endif
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-6 rounded shadow-sm hover:bg-indigo-700 transition">Save Role & Permissions</button>
                </div>
            </form>
        </div>

    </div>
</div>
