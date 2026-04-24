<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Workflows — {{ $module->name }}</h2>
        <a href="{{ route('builder.workflow.stages', $module) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Approval Stages →</a>
    </div>
</x-slot>

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- Workflow Form --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-base font-bold mb-5">{{ $editingWorkflowId ? 'Edit Workflow' : 'New Workflow' }}</h3>

            {{-- Name + Trigger --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Workflow Name</label>
                    <input type="text" wire:model="workflowName" placeholder="e.g. Notify on Submit"
                        class="block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('workflowName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trigger</label>
                    <select wire:model="workflowTrigger" class="block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="created">Record Created</option>
                        <option value="updated">Record Updated</option>
                        <option value="submitted">Record Submitted</option>
                        <option value="approved">Record Approved</option>
                        <option value="returned">Record Returned</option>
                    </select>
                </div>
            </div>

            {{-- Conditions --}}
            <div class="mb-5 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <label class="text-sm font-semibold text-amber-900">Run only if</label>
                        <select wire:model="conditionsLogic" class="rounded border-amber-300 text-xs shadow-sm text-amber-800 bg-amber-50 py-1">
                            <option value="and">ALL conditions match</option>
                            <option value="or">ANY condition matches</option>
                        </select>
                        <span class="text-xs text-amber-600">(leave empty to always run)</span>
                    </div>
                    <button type="button" wire:click="addCondition"
                        class="text-xs text-amber-700 font-medium border border-amber-300 rounded px-2.5 py-1 hover:bg-amber-100 transition">
                        + Add Condition
                    </button>
                </div>

                @if(count($conditions) === 0)
                    <p class="text-xs text-amber-600 italic">No conditions — workflow runs on every trigger.</p>
                @endif

                @foreach($conditions as $ci => $cond)
                    <div class="flex items-center gap-2 mt-2">
                        <select wire:model="conditions.{{ $ci }}.field" class="rounded border-gray-300 text-sm shadow-sm flex-1">
                            <option value="">-- Select Field --</option>
                            <option value="status">Status</option>
                            @foreach($moduleFields as $mf)
                                <option value="{{ $mf->slug }}">{{ $mf->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model="conditions.{{ $ci }}.operator" class="rounded border-gray-300 text-sm shadow-sm w-32">
                            <option value="=">equals</option>
                            <option value="!=">not equals</option>
                            <option value="contains">contains</option>
                            <option value="not_empty">is not empty</option>
                            <option value="empty">is empty</option>
                        </select>
                        @if(!in_array($conditions[$ci]['operator'] ?? '=', ['not_empty', 'empty']))
                            <input type="text" wire:model="conditions.{{ $ci }}.value" placeholder="value"
                                class="rounded border-gray-300 text-sm shadow-sm w-32">
                        @endif
                        <button type="button" wire:click="removeCondition({{ $ci }})" class="text-red-400 hover:text-red-600 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>

            {{-- Actions --}}
            <div class="mb-5">
                <label class="block text-sm font-semibold text-gray-700 mb-3">Actions</label>
                @foreach($actions as $i => $action)
                <div class="bg-gray-50 border rounded-lg p-4 mb-3">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Action {{ $i + 1 }}</span>
                        <button type="button" wire:click="removeAction({{ $i }})" class="text-red-400 hover:text-red-600 text-xs font-medium">Remove</button>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs text-gray-600 mb-1">Action Type</label>
                        <select wire:model.live="actions.{{ $i }}.type" class="block w-full rounded border-gray-300 text-sm shadow-sm">
                            <option value="notify_user">Notify User</option>
                            <option value="notify_role">Notify Role</option>
                            <option value="assign_to">Assign To User</option>
                            <option value="set_field">Set Field Value</option>
                            <option value="send_email">Send Email</option>
                        </select>
                    </div>

                    @if(($actions[$i]['type'] ?? '') === 'notify_user')
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">User</label>
                            <select wire:model="actions.{{ $i }}.config_json.user_id" class="block w-full rounded border-gray-300 text-sm shadow-sm">
                                <option value="">-- Select User --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mt-2">
                            <label class="block text-xs text-gray-600 mb-1">Message (optional)</label>
                            <input type="text" wire:model="actions.{{ $i }}.config_json.message" placeholder="Custom notification message..."
                                class="block w-full rounded border-gray-300 text-sm shadow-sm">
                        </div>
                    @elseif(($actions[$i]['type'] ?? '') === 'notify_role')
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Role</label>
                            <select wire:model="actions.{{ $i }}.config_json.role_name" class="block w-full rounded border-gray-300 text-sm shadow-sm">
                                <option value="">-- Select Role --</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mt-2">
                            <label class="block text-xs text-gray-600 mb-1">Message (optional)</label>
                            <input type="text" wire:model="actions.{{ $i }}.config_json.message" placeholder="Custom notification message..."
                                class="block w-full rounded border-gray-300 text-sm shadow-sm">
                        </div>
                    @elseif(($actions[$i]['type'] ?? '') === 'assign_to')
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Assign to User</label>
                            <select wire:model="actions.{{ $i }}.config_json.user_id" class="block w-full rounded border-gray-300 text-sm shadow-sm">
                                <option value="">-- Select User --</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif(($actions[$i]['type'] ?? '') === 'set_field')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Field</label>
                                <select wire:model="actions.{{ $i }}.config_json.field" class="block w-full rounded border-gray-300 text-sm shadow-sm">
                                    <option value="">-- Select Field --</option>
                                    @foreach($moduleFields as $f)
                                        <option value="{{ $f->slug }}">{{ $f->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Value</label>
                                <input type="text" wire:model="actions.{{ $i }}.config_json.value" class="block w-full rounded border-gray-300 text-sm shadow-sm">
                            </div>
                        </div>
                    @elseif(($actions[$i]['type'] ?? '') === 'send_email')
                        <div class="space-y-2">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">To Email</label>
                                <input type="email" wire:model="actions.{{ $i }}.config_json.to" class="block w-full rounded border-gray-300 text-sm shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Subject</label>
                                <input type="text" wire:model="actions.{{ $i }}.config_json.subject" class="block w-full rounded border-gray-300 text-sm shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Message</label>
                                <textarea wire:model="actions.{{ $i }}.config_json.message" rows="2" class="block w-full rounded border-gray-300 text-sm shadow-sm"></textarea>
                            </div>
                        </div>
                    @endif
                </div>
                @endforeach

                <button type="button" wire:click="addAction"
                    class="text-sm text-indigo-600 font-medium hover:text-indigo-900 border border-indigo-300 rounded px-3 py-1.5 hover:bg-indigo-50 transition">
                    + Add Action
                </button>
            </div>

            <div class="flex gap-3 border-t pt-4">
                <button wire:click="save" class="bg-indigo-600 text-white px-5 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm">Save Workflow</button>
                @if($editingWorkflowId)
                    <button wire:click="createNew" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-300">Cancel</button>
                @endif
            </div>
        </div>

        {{-- Existing Workflows --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-base font-bold mb-4">Configured Workflows</h3>
            @if($workflows->isEmpty())
                <p class="text-gray-500 italic text-sm">No workflows configured yet.</p>
            @else
                <div class="space-y-3">
                    @foreach($workflows as $wf)
                    <div class="flex items-start gap-4 bg-gray-50 border rounded-lg p-3">
                        <div class="flex-1">
                            <p class="font-semibold text-sm text-gray-800">{{ $wf->name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Trigger: <span class="font-semibold">{{ $wf->trigger }}</span>
                                · {{ $wf->actions->count() }} action(s)
                                @if(!empty($wf->conditions_json['conditions']))
                                    · <span class="text-amber-600 font-medium">{{ count($wf->conditions_json['conditions']) }} condition(s)</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <button wire:click="editWorkflow({{ $wf->id }})" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">Edit</button>
                            <button wire:click="delete({{ $wf->id }})" wire:confirm="Delete this workflow?" class="text-red-500 hover:text-red-700 text-xs font-medium">Delete</button>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="text-right">
            <a href="{{ route('builder.modules.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Back to Modules</a>
        </div>
    </div>
</div>
