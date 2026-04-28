<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Module;
use App\Models\Record;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;

class DynamicRecordIndex extends Component
{
    use WithPagination;

    public $moduleSlug;
    public $module;

    // Filters
    public string $search = '';
    public string $statusFilter = '';
    public string $stageFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public bool $myRecordsOnly = false; // driven by module setting only
    public array $fieldFilters = [];
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    public function mount($moduleSlug)
    {
        $this->moduleSlug = $moduleSlug;
        $this->module = Module::with(['fields' => fn($q) => $q->orderBy('sort_order')])->where('slug', $moduleSlug)->firstOrFail();

        $canView     = auth()->user()->can("view-{$this->moduleSlug}");
        $canApprove  = auth()->user()->can("approve-{$this->moduleSlug}");
        $canReview   = auth()->user()->can("review-{$this->moduleSlug}");
        $moduleId    = $this->module->id;
        $stageRoleIds = WorkflowStage::where('module_id', $moduleId)->pluck('approver_role_id')->filter();
        $isStageApprover = auth()->user()->roles->pluck('id')->intersect($stageRoleIds)->isNotEmpty();
        $isSuperAdmin = auth()->user()->hasRole('super admin');

        if (!$canView && !$canApprove && !$canReview && !$isStageApprover && !$isSuperAdmin) {
            abort(403, 'Unauthorized access to this module.');
        }

        // Merge source module fields
        if ($this->module->source_module_id) {
            $sourceFields = Module::find($this->module->source_module_id)->fields;
            $ownFields = $this->module->fields;
            $this->module->setRelation('fields', $sourceFields->merge($ownFields));
        }

        // Enforce module-level my_records_only setting
        if ($this->module->my_records_only) {
            $this->myRecordsOnly = true;
        }

        // Init per-field filters
        foreach ($this->module->fields as $field) {
            $this->fieldFilters[$field->slug] = '';
        }
    }

    // Reset page on any filter change
    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->stageFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        foreach ($this->fieldFilters as $slug => $_) {
            $this->fieldFilters[$slug] = '';
        }
        $this->resetPage();
    }

    public function sortByColumn(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    public function deleteRecord($id)
    {
        if (!auth()->user()->can("delete-{$this->moduleSlug}")) abort(403);
        $targetModuleId = $this->module->source_module_id ?? $this->module->id;
        Record::where('module_id', $targetModuleId)->where('id', $id)->delete();
    }

    public function updateStatus($id, $status)
    {
        if (!auth()->user()->can("change-status-{$this->moduleSlug}")) abort(403);
        $allowed = ['Draft', 'Submitted', 'Under Review', 'Returned', 'Completed', 'Archived'];
        if (!in_array($status, $allowed)) abort(422);
        $targetModuleId = $this->module->source_module_id ?? $this->module->id;
        $record = Record::where('module_id', $targetModuleId)->findOrFail($id);
        $record->update(['status' => $status]);
    }


    private function buildFilteredQuery(int $moduleId): Builder
    {
        $query = Record::where('module_id', $moduleId)
            ->with(['currentStage', 'creator']);

        // Draft records are hidden from mirrored modules
        if ($this->module->source_module_id) {
            $query->where('status', '!=', 'Draft');
        }

        // Full-text search across all JSON fields (SQLite-safe)
        if ($this->search !== '') {
            $search = $this->search;
            $fields = $this->module->fields;
            $query->where(function ($q) use ($search, $fields) {
                foreach ($fields as $field) {
                    $q->orWhereRaw(
                        "LOWER(json_extract(data, '$.{$field->slug}')) LIKE ?",
                        ['%' . strtolower($search) . '%']
                    );
                }
            });
        }

        // Status filter
        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        // Stage filter
        if ($this->stageFilter !== '') {
            if ($this->stageFilter === 'none') {
                $query->whereNull('current_stage_id');
            } else {
                $query->where('current_stage_id', $this->stageFilter);
            }
        }

        // Date range
        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        // My records only
        if ($this->myRecordsOnly) {
            $query->where('created_by', auth()->id());
        }

        // Per-field filters — slug is whitelisted against DB-loaded field slugs to prevent SQL injection
        $allowedSlugs = $this->module->fields->pluck('slug')->flip();
        foreach ($this->fieldFilters as $slug => $value) {
            if ($value === '' || !isset($allowedSlugs[$slug])) continue;
            $query->whereRaw(
                "LOWER(json_extract(data, '$.{$slug}')) LIKE ?",
                ['%' . strtolower($value) . '%']
            );
        }

        // Sorting
        $allowed = ['created_at', 'updated_at', 'status'];
        $col = in_array($this->sortBy, $allowed) ? $this->sortBy : 'created_at';
        $dir = $this->sortDir === 'asc' ? 'asc' : 'desc';
        $query->orderBy($col, $dir);

        return $query;
    }

    #[Layout('layouts.app')]
    public function render()
    {
        // Re-load fields on every render — Livewire drops eager-loaded relations on re-hydration
        $this->module = Module::with(['fields' => fn($q) => $q->orderBy('sort_order')])->find($this->module->id);
        if ($this->module->source_module_id) {
            $sourceFields = Module::find($this->module->source_module_id)->fields;
            $this->module->setRelation('fields', $sourceFields->merge($this->module->fields));
        }

        $targetModuleId = $this->module->source_module_id ?? $this->module->id;
        $records = $this->buildFilteredQuery($targetModuleId)->paginate(15);

        $stages = WorkflowStage::where('module_id', $targetModuleId)->orderBy('order')->get();
        $usersMap = \App\Models\User::pluck('name', 'id');

        $allStatuses = ['Draft', 'Submitted', 'Under Review', 'Completed', 'Returned', 'Archived'];

        $user = auth()->user();
        $stageRoleIds = $stages->pluck('approver_role_id')->filter();
        $isStageApprover = $user->roles->pluck('id')->intersect($stageRoleIds)->isNotEmpty();
        $canEditRecords = $user->hasRole('super admin')
            || $user->can("edit-{$this->moduleSlug}");

        return view('livewire.builder.dynamic-record-index',
            compact('records', 'stages', 'usersMap', 'allStatuses', 'canEditRecords'));
    }
}
