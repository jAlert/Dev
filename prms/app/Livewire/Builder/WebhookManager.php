<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\Module;
use Livewire\Attributes\Layout;

class WebhookManager extends Component
{
    use WithPagination;

    public $webhooks;
    public $modules;

    public $editingId = null;
    public $name = '';
    public $url = '';
    public $module_id = '';
    public $secret = '';
    public $is_active = true;
    public $events = [];

    public $availableEvents = ['created', 'updated', 'submitted', 'approved', 'returned'];

    public $viewingLogsFor = null;

    public function mount()
    {
        $this->modules = Module::whereNull('source_module_id')->orderBy('name')->get();
        $this->loadWebhooks();
    }

    public function loadWebhooks()
    {
        $this->webhooks = Webhook::with('module')->latest()->get();
    }

    public function createNew()
    {
        $this->editingId = null;
        $this->name = '';
        $this->url = '';
        $this->module_id = '';
        $this->secret = '';
        $this->is_active = true;
        $this->events = [];
        $this->viewingLogsFor = null;
    }

    public function edit($id)
    {
        $wh = Webhook::findOrFail($id);
        $this->editingId = $id;
        $this->name = $wh->name;
        $this->url = $wh->url;
        $this->module_id = $wh->module_id ?? '';
        $this->secret = $wh->secret ?? '';
        $this->is_active = $wh->is_active;
        $this->events = $wh->events ?? [];
        $this->viewingLogsFor = null;
    }

    public function save()
    {
        $this->validate([
            'name'   => 'required|string|max:255',
            'url'    => 'required|url|max:500',
            'events' => 'required|array|min:1',
        ]);

        Webhook::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name'      => $this->name,
                'url'       => $this->url,
                'module_id' => $this->module_id ?: null,
                'secret'    => $this->secret ?: null,
                'is_active' => $this->is_active,
                'events'    => $this->events,
            ]
        );

        $this->loadWebhooks();
        $this->createNew();
        session()->flash('message', 'Webhook saved.');
    }

    public function toggleActive($id)
    {
        $wh = Webhook::findOrFail($id);
        $wh->update(['is_active' => !$wh->is_active]);
        $this->loadWebhooks();
    }

    public function delete($id)
    {
        Webhook::destroy($id);
        $this->loadWebhooks();
    }

    public function viewLogs($id)
    {
        $this->viewingLogsFor = $id;
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $logs = $this->viewingLogsFor
            ? WebhookLog::where('webhook_id', $this->viewingLogsFor)->latest('created_at')->limit(20)->get()
            : collect();

        return view('livewire.builder.webhook-manager', compact('logs'));
    }
}
