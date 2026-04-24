<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use App\Models\Module;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\Attributes\Layout;

class WorkflowManager extends Component
{
    public Module $module;

    public $workflows = [];
    public $users = [];
    public $roles = [];
    public $moduleFields = [];

    // Workflow form
    public $editingWorkflowId = null;
    public $workflowName = '';
    public $workflowTrigger = 'created';

    // Conditions
    public $conditions = []; // [{field, operator, value}]
    public $conditionsLogic = 'and'; // 'and' | 'or'

    // Actions
    public $actions = [];

    public function mount(Module $module)
    {
        $this->module = $module;
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->workflows = Workflow::where('module_id', $this->module->id)
            ->with('actions')
            ->get();
        $this->users = User::orderBy('name')->get();
        $this->roles = Role::where('name', '!=', 'super admin')->get();
        $this->moduleFields = $this->module->fields;
    }

    public function createNew(): void
    {
        $this->editingWorkflowId = null;
        $this->workflowName = '';
        $this->workflowTrigger = 'created';
        $this->conditions = [];
        $this->conditionsLogic = 'and';
        $this->actions = [];
        $this->addAction();
    }

    public function editWorkflow($id): void
    {
        $wf = Workflow::with('actions')->findOrFail($id);
        $this->editingWorkflowId = $id;
        $this->workflowName = $wf->name;
        $this->workflowTrigger = $wf->trigger;
        $this->conditions = $wf->conditions_json['conditions'] ?? [];
        $this->conditionsLogic = $wf->conditions_json['logic'] ?? 'and';
        $this->actions = $wf->actions->map(fn($a) => [
            'type' => $a->type,
            'config_json' => $a->config_json ?? [],
        ])->toArray();
    }

    public function addCondition(): void
    {
        $this->conditions[] = ['field' => '', 'operator' => '=', 'value' => ''];
    }

    public function removeCondition($index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    public function addAction(): void
    {
        $this->actions[] = ['type' => 'notify_user', 'config_json' => ['user_id' => '']];
    }

    public function removeAction($index): void
    {
        unset($this->actions[$index]);
        $this->actions = array_values($this->actions);
    }

    public function save(): void
    {
        $this->validate([
            'workflowName'    => 'required|string|max:255',
            'workflowTrigger' => 'required|in:created,updated,submitted,approved,returned',
        ]);

        $conditionsJson = null;
        $validConditions = array_values(array_filter($this->conditions, fn($c) => !empty($c['field'])));
        if (!empty($validConditions)) {
            $conditionsJson = [
                'logic'      => $this->conditionsLogic,
                'conditions' => $validConditions,
            ];
        }

        $workflow = Workflow::updateOrCreate(
            ['id' => $this->editingWorkflowId],
            [
                'module_id'       => $this->module->id,
                'name'            => $this->workflowName,
                'trigger'         => $this->workflowTrigger,
                'conditions_json' => $conditionsJson,
            ]
        );

        $workflow->actions()->delete();
        foreach ($this->actions as $action) {
            if (empty($action['type'])) continue;
            $workflow->actions()->create([
                'type'        => $action['type'],
                'config_json' => $action['config_json'] ?? [],
            ]);
        }

        $this->loadData();
        $this->createNew();
        session()->flash('message', 'Workflow saved.');
    }

    public function delete($id): void
    {
        Workflow::destroy($id);
        $this->loadData();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.builder.workflow-manager');
    }
}
