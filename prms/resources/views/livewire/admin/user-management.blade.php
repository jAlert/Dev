<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('User Access Management') }}
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col md:flex-row gap-6">
        
        <!-- User List -->
        <div class="w-full md:w-1/3 bg-white overflow-hidden shadow-sm sm:rounded-lg flex flex-col">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-700">Accounts</h3>
                <button wire:click="createNew" class="text-sm bg-indigo-600 text-white px-3 py-1 rounded shadow-sm hover:bg-indigo-700">+ New User</button>
            </div>
            <div class="overflow-y-auto flex-1 p-2" style="max-height: 600px;">
                @foreach($users as $u)
                    <div wire:click="selectUser({{ $u->id }})" class="p-3 mb-2 rounded cursor-pointer border hover:border-indigo-500 transition-colors {{ optional($selectedUser)->id === $u->id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' }} {{ !$u->is_active ? 'opacity-60' : '' }}">
                        <div class="font-medium text-gray-900 flex items-center gap-2">
                            {{ $u->name }}
                            @if(!$u->is_active)
                                <span class="px-1.5 py-0.5 text-[10px] bg-red-100 text-red-700 rounded font-bold">Disabled</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500">{{ $u->email }}</div>
                        <div class="mt-1 flex gap-1">
                            @if($u->hasRole('super admin'))
                                <span class="px-2 py-0.5 text-[10px] bg-red-100 text-red-800 rounded-full font-bold">Super Admin</span>
                            @else
                                <span class="px-2 py-0.5 text-[10px] bg-green-100 text-green-800 rounded-full font-bold">User</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Form & Permissions -->
        <div class="w-full md:w-2/3 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center justify-between border-b pb-2 mb-4">
                <h3 class="text-lg font-bold">{{ $selectedUser ? 'Edit User Access' : 'Create New Account' }}</h3>
                @if($selectedUser && !$selectedUser->hasRole('super admin'))
                    <button type="button"
                        wire:click="toggleActive({{ $selectedUser->id }})"
                        wire:confirm="{{ $selectedUser->is_active ? 'Disable this account? The user will not be able to log in.' : 'Enable this account?' }}"
                        class="text-sm font-bold px-4 py-1.5 rounded shadow-sm transition
                            {{ $selectedUser->is_active
                                ? 'bg-red-100 text-red-700 hover:bg-red-200'
                                : 'bg-green-100 text-green-700 hover:bg-green-200' }}">
                        {{ $selectedUser->is_active ? 'Disable Account' : 'Enable Account' }}
                    </button>
                @endif
            </div>
            
            <form wire:submit="saveUser">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase">Name</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded border-gray-300 text-sm">
                        @error('name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase">Email</label>
                        <input type="email" wire:model="email" class="mt-1 block w-full rounded border-gray-300 text-sm">
                        @error('email') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 uppercase">Password {{ $selectedUser ? '(Leave blank to keep current)' : '' }}</label>
                        <input type="password" wire:model="password" class="mt-1 block w-full rounded border-gray-300 text-sm">
                        @error('password') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if(!$selectedUser || !$selectedUser->hasRole('super admin'))

                <h4 class="font-bold text-gray-700 bg-gray-100 p-2 rounded mb-4">Assign Logical Roles</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-8">
                    @foreach($roles as $r)
                        <label class="flex items-center text-sm cursor-pointer border p-3 rounded hover:bg-gray-50">
                            <input type="checkbox" wire:model="userRoles.{{ $r->name }}" class="rounded text-indigo-600 mr-3 border-gray-300 shadow-sm focus:ring-indigo-500"> 
                            <span class="font-medium text-gray-700">{{ $r->name }}</span>
                        </label>
                    @endforeach
                    @if($roles->isEmpty())
                        <div class="col-span-full text-sm text-gray-500">No roles available. Create them in Role Management.</div>
                    @endif
                </div>

                <h4 class="font-bold text-gray-700 bg-gray-100 p-2 rounded mb-4">Direct Module Access Exceptions</h4>
                <div class="space-y-4">
                    @foreach($modules as $m)
                        <div class="border rounded p-4">
                            <h5 class="font-semibold text-indigo-700 mb-2 border-b pb-1">{{ $m->name }}</h5>
                            <div class="flex gap-4">
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="userPermissions.view-{{ $m->slug }}" class="rounded text-indigo-600 mr-2 border-gray-300 shadow-sm focus:ring-indigo-500"> View
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="userPermissions.create-{{ $m->slug }}" class="rounded text-green-600 mr-2 border-gray-300 shadow-sm focus:ring-green-500"> Create
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="userPermissions.edit-{{ $m->slug }}" class="rounded text-yellow-600 mr-2 border-gray-300 shadow-sm focus:ring-yellow-500"> Edit
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="userPermissions.delete-{{ $m->slug }}" class="rounded text-red-600 mr-2 border-gray-300 shadow-sm focus:ring-red-500"> Delete
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="userPermissions.change-status-{{ $m->slug }}" class="rounded text-purple-600 mr-2 border-gray-300 shadow-sm focus:ring-purple-500"> Change Status
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="userPermissions.review-{{ $m->slug }}" class="rounded text-teal-600 mr-2 border-gray-300 shadow-sm focus:ring-teal-500"> Review
                                </label>
                                <label class="flex items-center text-sm cursor-pointer">
                                    <input type="checkbox" wire:model="userPermissions.approve-{{ $m->slug }}" class="rounded text-blue-600 mr-2 border-gray-300 shadow-sm focus:ring-blue-500"> Approve
                                </label>
                            </div>
                        </div>
                    @endforeach
                    @if($modules->isEmpty())
                        <p class="text-sm text-gray-500 italic">No modules built yet.</p>
                    @endif
                </div>
                @else
                    <div class="bg-red-50 text-red-700 p-4 rounded border border-red-200 shadow-sm flex items-center">
                        <svg class="h-6 w-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        This user is a Super Admin. They inherently bypass all module restrictions!
                    </div>
                @endif

                <div class="mt-8 flex justify-end">
                    <button type="submit" class="bg-indigo-600 text-white font-bold py-2 px-6 rounded shadow-sm hover:bg-indigo-700 transition">Save Account & Access</button>
                </div>
            </form>
        </div>

    </div>
</div>
