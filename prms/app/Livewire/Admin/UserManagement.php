<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Module;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;

class UserManagement extends Component
{
    public $users;
    public $modules;
    public $selectedUser = null;

    // Form data
    public $name = '';
    public $email = '';
    public $password = '';
    
    public $roles = [];
    public $userRoles = [];
    public $userPermissions = [];

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->users = User::with('roles', 'permissions')->get();
        $this->modules = Module::orderBy('sort_order')->get();
        $this->roles = Role::where('name', '!=', 'super admin')->get();
    }

    public function selectUser($id)
    {
        $user = User::findOrFail($id);
        $this->selectedUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = ''; 
        
        $this->userRoles = $user->roles->pluck('name')->mapWithKeys(fn($n) => [$n => true])->toArray();
        $this->userPermissions = $user->getDirectPermissions()->pluck('name')->mapWithKeys(fn($n) => [$n => true])->toArray();
    }

    public function createNew()
    {
        $this->selectedUser = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->userRoles = [];
        $this->userPermissions = [];
    }

    public function saveUser()
    {
        $this->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . ($this->selectedUser?->id ?? 'NULL'),
            'password' => $this->selectedUser ? 'nullable|min:6' : 'required|min:6',
        ]);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->password) {
            $data['password'] = bcrypt($this->password);
        }

        if ($this->selectedUser) {
            if ($this->selectedUser->email === env('ADMIN_EMAIL') && $this->email !== env('ADMIN_EMAIL')) {
                $this->addError('email', 'The system admin email address cannot be changed.');
                return;
            }
            $this->selectedUser->update($data);
            $user = $this->selectedUser;
        } else {
            $user = User::create($data);
        }

        // Sync permissions
        $validPermissions = array_filter($this->userPermissions); // remove falsy values
        
        // Ensure perms exist in DB before attaching (fallback)
        foreach(array_keys($validPermissions) as $permName) {
            Permission::firstOrCreate(['name' => $permName]);
        }

        $user->syncPermissions(array_keys($validPermissions));

        $validRoles = array_filter($this->userRoles);
        $user->syncRoles(array_keys($validRoles));

        $this->loadData();
        $this->createNew();
    }

    public function toggleActive($id)
    {
        $user = User::findOrFail($id);
        if ($user->email === env('ADMIN_EMAIL')) {
            session()->flash('error', 'The system admin account cannot be deactivated.');
            return;
        }
        $user->update(['is_active' => !$user->is_active]);
        $this->loadData();
        if ($this->selectedUser?->id === $id) {
            $this->selectedUser = $user->fresh();
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.user-management');
    }
}
