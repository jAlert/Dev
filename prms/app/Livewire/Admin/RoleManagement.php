<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Module;
use Livewire\Attributes\Layout;

class RoleManagement extends Component
{
    public $roles;
    public $modules;
    public $selectedRole = null;
    public $name = '';
    public $rolePermissions = [];

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->roles = Role::where('name', '!=', 'super admin')->with('permissions')->get();
        $this->modules = Module::orderBy('sort_order')->get();
    }

    public function selectRole($id)
    {
        $role = Role::findOrFail($id);
        $this->selectedRole = $role;
        $this->name = $role->name;
        $this->rolePermissions = $role->permissions->pluck('name')->mapWithKeys(fn($n) => [$n => true])->toArray();
    }

    public function createNew()
    {
        $this->selectedRole = null;
        $this->name = '';
        $this->rolePermissions = [];
    }

    public function saveRole()
    {
        $this->validate([
            'name' => 'required|string|unique:roles,name,' . ($this->selectedRole?->id ?? 'NULL'),
        ]);

        if ($this->selectedRole) {
            $this->selectedRole->update(['name' => $this->name]);
            $role = $this->selectedRole;
        } else {
            $role = Role::create(['name' => $this->name]);
        }

        $validPermissions = array_filter($this->rolePermissions);

        foreach(array_keys($validPermissions) as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        $role->syncPermissions(array_keys($validPermissions));

        $this->loadData();
        $this->createNew();
    }

    public function deleteRole($id)
    {
        $role = Role::findOrFail($id);
        if ($role->name !== 'super admin') {
            $role->delete();
        }
        $this->loadData();
        $this->createNew();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.role-management');
    }
}
