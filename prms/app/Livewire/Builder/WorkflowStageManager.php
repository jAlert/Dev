<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use App\Models\Module;
use App\Models\WorkflowStage;
use Spatie\Permission\Models\Role;
use Livewire\Attributes\Layout;

class WorkflowStageManager extends Component
{
    public Module $module;

    public $stages = [];
    public $roles = [];

    // Form
    public $editingId = null;
    public $stageName = '';
    public $stageOrder = 0;
    public $approverRoleId = null;
    public $reviewerRoleId = null;
    public $isFinalApproval = false;
    public $hasReturnButton = true;
    public $allowEdit = true;
    public $defaultStatus = null;
    public $stageFields = [];
    public $stageType = 'approval';
    public $autoAdvanceDays = null;
    public $branches = [];

    public function mount(Module $module)
    {
        $this->module = $module;
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->stages = WorkflowStage::where('module_id', $this->module->id)
            ->with('approverRole')
            ->orderBy('order')
            ->get();
        $this->roles = Role::where('name', '!=', 'super admin')->get();
    }

    public function createNew(): void
    {
        $this->editingId = null;
        $this->stageName = '';
        $this->stageOrder = ($this->stages->max('order') ?? -1) + 1;
        $this->approverRoleId = null;
        $this->reviewerRoleId = null;
        $this->isFinalApproval = false;
        $this->hasReturnButton = true;
        $this->allowEdit = true;
        $this->defaultStatus = null;
        $this->stageFields = [];
        $this->stageType = 'approval';
        $this->autoAdvanceDays = null;
        $this->branches = [];
    }

    public function edit($id): void
    {
        $stage = WorkflowStage::findOrFail($id);
        $this->editingId = $id;
        $this->stageName = $stage->name;
        $this->stageOrder = $stage->order;
        $this->approverRoleId = $stage->approver_role_id;
        $this->reviewerRoleId = $stage->reviewer_role_id;
        $this->isFinalApproval = $stage->is_final_approval;
        $this->hasReturnButton = (bool) ($stage->has_return_button ?? true);
        $this->allowEdit = (bool) ($stage->allow_edit ?? true);
        $this->defaultStatus = $stage->default_status;
        $this->stageFields = array_map(function ($sf) {
            $options = $sf['options_json'] ?? [];
            return [
                'name'       => $sf['name'] ?? '',
                'type'       => $sf['type'] ?? 'text',
                'is_required'=> $sf['is_required'] ?? false,
                'options_raw'=> is_array($options) ? implode("\n", $options) : '',
            ];
        }, $stage->stage_fields_json ?? []);
        $this->stageType = $stage->stage_type ?? 'approval';
        $this->autoAdvanceDays = $stage->auto_advance_days;
        $this->branches = array_map(
            fn($b) => ['label' => $b['label'] ?? '', 'stage_id' => (string) ($b['stage_id'] ?? '')],
            $stage->branches_json ?? []
        );
    }

    public function addBranch(): void
    {
        $this->branches[] = ['label' => '', 'stage_id' => ''];
    }

    public function removeBranch($index): void
    {
        array_splice($this->branches, $index, 1);
    }

    public function addStageField(): void
    {
        $this->stageFields[] = ['name' => '', 'type' => 'text', 'is_required' => false, 'options_raw' => ''];
    }

    public function removeStageField($index): void
    {
        array_splice($this->stageFields, $index, 1);
    }

    public function save(): void
    {
        if (!auth()->user()->can("edit-{$this->module->slug}") && !auth()->user()->hasRole('super admin')) abort(403);
        $rules = [
            'stageName'       => 'required|string|max:255',
            'stageOrder'      => 'required|integer|min:0',
            'approverRoleId'  => 'nullable|exists:roles,id',
            'reviewerRoleId'  => 'nullable|exists:roles,id',
            'stageType'       => 'required|in:review,approval,none',
            'autoAdvanceDays' => 'nullable|integer|min:1|max:365',
            'branches'        => 'nullable|array',
        ];
        foreach ($this->branches as $i => $_) {
            $rules["branches.{$i}.label"]    = 'required|string|max:100';
            $rules["branches.{$i}.stage_id"] = 'required|exists:workflow_stages,id';
        }

        $this->validate($rules);

        $branches = array_values(array_filter(
            $this->branches,
            fn($b) => !empty($b['label']) && !empty($b['stage_id'])
        ));

        WorkflowStage::updateOrCreate(
            ['id' => $this->editingId],
            [
                'module_id'        => $this->module->id,
                'name'             => $this->stageName,
                'order'            => $this->stageOrder,
                'approver_role_id' => $this->approverRoleId ?: null,
                'reviewer_role_id' => $this->reviewerRoleId ?: null,
                'is_final_approval'      => $this->isFinalApproval,
                'has_return_button'  => $this->hasReturnButton,
                'allow_edit'         => $this->allowEdit,
                'default_status'     => $this->defaultStatus ?: null,
                'stage_fields_json'  => $this->buildStageFields(),
                'stage_type'         => $this->stageType,
                'auto_advance_days' => $this->autoAdvanceDays ?: null,
                'branches_json'    => empty($branches) ? null : $branches,
            ]
        );

        $this->loadData();
        $this->createNew();
        session()->flash('message', 'Stage saved.');
    }

    public function delete($id): void
    {
        if (!auth()->user()->can("edit-{$this->module->slug}") && !auth()->user()->hasRole('super admin')) abort(403);
        WorkflowStage::destroy($id);
        $this->loadData();
    }

    public function saveTemplate(): mixed
    {
        $stages = WorkflowStage::where('module_id', $this->module->id)
            ->with('approverRole', 'reviewerRole')
            ->orderBy('order')
            ->get()
            ->map(fn($s) => [
                'name'               => $s->name,
                'order'              => $s->order,
                'stage_type'         => $s->stage_type,
                'approver_role'      => $s->approverRole?->name,
                'reviewer_role'      => $s->reviewerRole?->name,
                'is_final_approval'  => $s->is_final_approval,
                'has_return_button'  => $s->has_return_button,
                'allow_edit'         => $s->allow_edit,
                'default_status'     => $s->default_status,
                'auto_advance_days'  => $s->auto_advance_days,
                'stage_fields_json'  => $s->stage_fields_json,
                'branches_json'      => $s->branches_json,
            ]);

        $payload = json_encode([
            'module'      => $this->module->name,
            'module_slug' => $this->module->slug,
            'exported_at' => now()->toIso8601String(),
            'stages'      => $stages,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $filename = "stages_{$this->module->slug}_" . now()->format('Ymd_His') . '.json';

        return response()->streamDownload(
            fn() => print($payload),
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    private function buildStageFields(): ?array
    {
        $fields = [];
        foreach ($this->stageFields as $sf) {
            $name = trim($sf['name'] ?? '');
            if ($name === '') continue;
            $type = $sf['type'] ?? 'text';
            $optionsRaw = trim($sf['options_raw'] ?? '');
            $options = (in_array($type, ['select', 'multi_select']) && $optionsRaw !== '')
                ? array_values(array_filter(array_map('trim', explode("\n", $optionsRaw))))
                : null;
            $fields[] = [
                'name'        => $name,
                'slug'        => \Illuminate\Support\Str::slug($name, '_'),
                'type'        => $type,
                'is_required' => (bool) ($sf['is_required'] ?? false),
                'options_json'=> $options,
            ];
        }
        return empty($fields) ? null : $fields;
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.builder.workflow-stage-manager');
    }
}
