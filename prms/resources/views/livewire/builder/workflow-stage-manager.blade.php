<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Approval Stages — {{ $module->name }}
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        {{-- Stage Form --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-base font-bold mb-4">{{ $editingId ? 'Edit Stage' : 'Add New Stage' }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Stage Name</label>
                    <input type="text" wire:model="stageName" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('stageName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Order (0 = first)</label>
                    <input type="number" wire:model="stageOrder" min="0" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('stageOrder') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Approver Role</label>
                    <select wire:model="approverRoleId" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">-- Any approver with permission --</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Reviewer Role <span class="text-xs text-gray-400 font-normal">(for "Mark Review Done" on text editor fields)</span></label>
                    <select wire:model="reviewerRoleId" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="">-- None --</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">All users in this role will see "Mark Review Done". When all have clicked it, the record advances to the next stage.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Stage Type</label>
                    <select wire:model="stageType" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="approval">Approval — can approve/return</option>
                        <option value="review">Review — forwards to next stage</option>
                        <option value="none">None — branch paths only (no default button)</option>
                    </select>
                    @error('stageType') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                {{-- Branch Paths — available for both review and approval stages --}}
                <div class="md:col-span-2 border rounded-lg p-4 bg-indigo-50 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-indigo-800">Branch Paths
                            <span class="font-normal text-indigo-600 text-xs ml-1">
                                — review: replaces "Forward" button · approval: added alongside Approve
                            </span>
                        </p>
                        <button type="button" wire:click="addBranch"
                            class="text-xs bg-indigo-600 text-white px-2.5 py-1 rounded hover:bg-indigo-700 font-bold">
                            + Add Branch
                        </button>
                    </div>
                    @if(empty($branches))
                        <p class="text-xs text-indigo-400 italic">No branches — click "+ Add Branch" to add routing paths.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($branches as $i => $branch)
                            <div class="flex items-start gap-3 bg-white rounded p-3 border border-indigo-200">
                                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Button Label</label>
                                        <input type="text" wire:model="branches.{{ $i }}.label"
                                            placeholder="e.g. Forward Ad Referendum"
                                            class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        @error("branches.{$i}.label") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Routes to Stage</label>
                                        <select wire:model="branches.{{ $i }}.stage_id"
                                            class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">— select stage —</option>
                                            @foreach($stages as $s)
                                                @if(!$editingId || $s->id != $editingId)
                                                    <option value="{{ $s->id }}">{{ $s->order + 1 }}. {{ $s->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        @error("branches.{$i}.stage_id") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <button type="button" wire:click="removeBranch({{ $i }})"
                                    class="mt-5 text-red-400 hover:text-red-600 text-sm font-bold leading-none">✕</button>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Deadline (working days)
                        <span class="text-gray-400 font-normal text-xs ml-1">— leave blank to disable auto-advance</span>
                    </label>
                    <input type="number" wire:model="autoAdvanceDays" min="1" max="365" placeholder="e.g. 10"
                        class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('autoAdvanceDays') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <p class="text-xs text-gray-400 mt-1">If no action is taken within this many working days, the record auto-advances to the next stage (or is auto-approved at the final stage).</p>
                </div>
                <div class="space-y-3 mt-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Default Status when entering this stage
                            <span class="text-gray-400 font-normal text-xs ml-1">— leave blank to use "Under Review"</span>
                        </label>
                        <select wire:model="defaultStatus" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Under Review (default) —</option>
                            <option value="Submitted">Submitted</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Completed">Completed</option>
                            <option value="Archived">Archived</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="hasReturnButton" id="has_return_button" class="rounded border-gray-300 text-indigo-600">
                        <label for="has_return_button" class="text-sm font-medium text-gray-700">Show "Return for Revision" button</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="allowEdit" id="allow_edit" class="rounded border-gray-300 text-indigo-600">
                        <label for="allow_edit" class="text-sm font-medium text-gray-700">Allow editing record in this stage</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="isFinalApproval" id="final" class="rounded border-gray-300 text-indigo-600">
                        <label for="final" class="text-sm font-medium text-gray-700">This is the final approval stage</label>
                    </div>
                </div>
                {{-- Stage Fields — custom fields filled by the reviewer in the approval panel --}}
                <div class="md:col-span-2 border rounded-lg p-4 bg-teal-50 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-teal-800">Stage Fields
                            <span class="font-normal text-teal-600 text-xs ml-1">— reviewer fills these from the approval panel without opening the form</span>
                        </p>
                        <button type="button" wire:click="addStageField"
                            class="text-xs bg-teal-600 text-white px-2.5 py-1 rounded hover:bg-teal-700 font-bold">
                            + Add Field
                        </button>
                    </div>
                    @if(empty($stageFields))
                        <p class="text-xs text-teal-400 italic">No stage fields — click "+ Add Field" to define custom fields for this stage.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($stageFields as $i => $sf)
                            <div class="bg-white rounded p-3 border border-teal-200 space-y-2">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-gray-600">Field Name</label>
                                        <input type="text" wire:model="stageFields.{{ $i }}.name"
                                            placeholder="e.g. Reviewer Notes"
                                            class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">
                                        @error("stageFields.{$i}.name") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Type</label>
                                        <select wire:model="stageFields.{{ $i }}.type"
                                            class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">
                                            <option value="text">Text</option>
                                            <option value="textarea">Textarea</option>
                                            <option value="number">Number</option>
                                            <option value="select">Select</option>
                                            <option value="multi_select">Multi-Select</option>
                                            <option value="date">Date</option>
                                            <option value="boolean">Checkbox</option>
                                            <option value="attachment">File Attachment</option>
                                        </select>
                                    </div>
                                </div>
                                @if(in_array($sf['type'] ?? 'text', ['select', 'multi_select']))
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Options <span class="font-normal text-gray-400">(one per line)</span></label>
                                    <textarea wire:model="stageFields.{{ $i }}.options_raw" rows="3"
                                        placeholder="Option A&#10;Option B&#10;Option C"
                                        class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm font-mono"></textarea>
                                </div>
                                @endif
                                <div class="flex items-center justify-between pt-1">
                                    <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                        <input type="checkbox" wire:model="stageFields.{{ $i }}.is_required"
                                            class="rounded border-gray-300 text-teal-600 text-xs">
                                        Required
                                    </label>
                                    <button type="button" wire:click="removeStageField({{ $i }})"
                                        class="text-red-400 hover:text-red-600 text-xs font-bold">Remove</button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button wire:click="save" class="bg-indigo-600 text-white px-5 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm">Save Stage</button>
                @if($editingId)
                    <button wire:click="createNew" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-300">Cancel</button>
                @endif
            </div>
        </div>

        {{-- Stages List --}}
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold">Configured Stages</h3>
                    @if($stages->isNotEmpty())
                    <button wire:click="saveTemplate" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded bg-gray-100 text-gray-700 hover:bg-gray-200 disabled:opacity-50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Save Template
                    </button>
                    @endif
                </div>
                @if($stages->isEmpty())
                    <p class="text-gray-500 italic text-sm">No stages configured. Add the first stage above.</p>
                @else
                    <div class="space-y-2">
                        @foreach($stages as $stage)
                        <div class="flex items-center gap-4 bg-gray-50 border rounded p-3">
                            <span class="w-8 h-8 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-sm font-bold">{{ $stage->order + 1 }}</span>
                            <div class="flex-1">
                                <p class="font-medium text-sm">{{ $stage->name }}</p>
                                <p class="text-xs text-gray-500 flex items-center gap-2">
                                    Role: {{ $stage->approverRole?->name ?? 'Any with permission' }}
                                    @if($stage->reviewerRole) · Reviewer: {{ $stage->reviewerRole->name }} @endif
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase
                                        {{ $stage->stage_type === 'review' ? 'bg-blue-100 text-blue-700' : ($stage->stage_type === 'none' ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700') }}">
                                        {{ $stage->stage_type === 'review' ? 'Reviewer' : ($stage->stage_type === 'none' ? 'Routing' : 'Approver') }}
                                    </span>
                                    @if($stage->is_final_approval) <span class="text-green-600 font-semibold">· Final Stage</span> @endif
                                    @if(!$stage->has_return_button) <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-500">no return</span> @endif
                                    @if(!empty($stage->stage_fields_json)) <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-teal-100 text-teal-700">✎ {{ count($stage->stage_fields_json) }} field{{ count($stage->stage_fields_json) > 1 ? 's' : '' }}</span> @endif
                                    @if($stage->auto_advance_days)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">
                                            ⏱ {{ $stage->auto_advance_days }}wd deadline
                                        </span>
                                    @endif
                                    @if(!empty($stage->branches_json))
                                        @foreach($stage->branches_json as $b)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700">⑂ {{ $b['label'] }}</span>
                                        @endforeach
                                    @endif
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button wire:click="edit({{ $stage->id }})" class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">Edit</button>
                                <button wire:click="delete({{ $stage->id }})" wire:confirm="Delete this stage?" class="text-red-500 hover:text-red-700 text-xs font-medium">Delete</button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="text-right">
            <a href="{{ route('builder.modules.index') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">← Back to Modules</a>
        </div>
    </div>
</div>
