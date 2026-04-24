<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\RecordHistory;
use App\Models\Module;
use App\Models\User;
use Livewire\Attributes\Layout;

class AuditLog extends Component
{
    use WithPagination;

    public string $search    = '';
    public string $moduleFilter = '';
    public string $actionFilter = '';
    public string $userFilter   = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedModuleFilter(): void { $this->resetPage(); }
    public function updatedActionFilter(): void { $this->resetPage(); }
    public function updatedUserFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->search = $this->moduleFilter = $this->actionFilter = $this->userFilter = $this->dateFrom = $this->dateTo = '';
        $this->resetPage();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $query = RecordHistory::with(['user', 'record.module'])->latest();

        if ($this->userFilter !== '') {
            $query->where('user_id', $this->userFilter);
        }

        if ($this->actionFilter !== '') {
            $query->where('action', $this->actionFilter);
        }

        if ($this->moduleFilter !== '') {
            $moduleId = Module::where('slug', $this->moduleFilter)->value('id');
            if ($moduleId) {
                $query->whereHas('record', fn($q) => $q->where('module_id', $moduleId));
            }
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $logs    = $query->paginate(25);
        $modules = Module::whereNull('source_module_id')->orderBy('name')->get();
        $users   = User::orderBy('name')->get();
        $actions = ['created', 'updated', 'submitted', 'approved', 'returned'];

        return view('livewire.builder.audit-log', compact('logs', 'modules', 'users', 'actions'));
    }
}
