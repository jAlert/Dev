<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $recordId ? 'Edit' : 'Create' }} {{ $module->name }}
        </h2>
        @if($recordId)
            <span class="px-3 py-1 rounded-full text-xs font-bold
                {{ $record->status === 'Completed' ? 'bg-green-100 text-green-800' : '' }}
                {{ $record->status === 'Submitted' ? 'bg-blue-100 text-blue-800' : '' }}
                {{ $record->status === 'Under Review' ? 'bg-indigo-100 text-indigo-800' : '' }}
                {{ $record->status === 'Returned' ? 'bg-orange-100 text-orange-800' : '' }}
                {{ $record->status === 'Draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                {{ $record->status === 'Archived' ? 'bg-gray-100 text-gray-600' : '' }}
                {{ !in_array($record->status, ['Completed','Submitted','Under Review','Returned','Draft','Archived']) ? 'bg-gray-100 text-gray-700' : '' }}
            ">{{ $record->status }}</span>
        @endif
    </div>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="flex gap-6 items-start">

    {{-- LEFT COLUMN: Approval Actions + Form --}}
    <div class="flex-1 min-w-0 space-y-6">

{{-- Approval Actions Panel (reviewer/approver actions for existing records only) --}}
        @if($recordId)
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Approval Actions
            </h3>

            {{-- Approver/Reviewer Actions --}}
            @if($canAct)
                @php $isReviewStage = ($currentStage?->stage_type === 'review'); @endphp
                <div class="mt-4 border-t pt-4 space-y-3">
                    @if($currentStage)
                        <p class="text-xs text-gray-500">
                            Current stage: <strong>{{ $currentStage->name }}</strong>
                            <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $isReviewStage ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                                {{ $isReviewStage ? 'Reviewer' : 'Approver' }}
                            </span>
                        </p>
                    @endif
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">
                            {{ $isReviewStage ? 'Comment (optional)' : 'Comment / Reason (required for Return)' }}
                        </label>
                        <textarea wire:model="approvalComment" rows="2" placeholder="Add a comment..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                        @error('approvalComment') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    @php
                        $isNoneStage = ($currentStage?->stage_type === 'none');
                        $branches = $currentStage?->branches_json ?? [];
                    @endphp
                    <div class="flex gap-3 flex-wrap">
                        @if($isNoneStage)
                            @if(!empty($branches))
                                @foreach($branches as $i => $branch)
                                <button wire:click="forwardToBranch({{ $i }})" wire:loading.attr="disabled"
                                    class="bg-indigo-600 text-white px-4 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm disabled:opacity-50">
                                    → {{ $branch['label'] }}
                                </button>
                                @endforeach
                            @endif
                        @elseif($isReviewStage)
                            @if(!empty($branches))
                                @foreach($branches as $i => $branch)
                                <button wire:click="forwardToBranch({{ $i }})" wire:loading.attr="disabled"
                                    class="bg-indigo-600 text-white px-4 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm disabled:opacity-50">
                                    → {{ $branch['label'] }}
                                </button>
                                @endforeach
                            @else
                                <button wire:click="approve" wire:loading.attr="disabled"
                                    class="bg-blue-600 text-white px-4 py-2 rounded shadow-sm hover:bg-blue-700 font-bold text-sm disabled:opacity-50">
                                    Forward to Next Stage →
                                </button>
                            @endif
                            @if($module->has_return_button && ($currentStage?->has_return_button ?? true))
                            <button wire:click="returnForRevision" wire:loading.attr="disabled"
                                class="bg-orange-500 text-white px-4 py-2 rounded shadow-sm hover:bg-orange-600 font-bold text-sm disabled:opacity-50">Return for Revision</button>
                            @endif
                        @else
                            <button wire:click="approve" wire:loading.attr="disabled"
                                class="bg-green-600 text-white px-4 py-2 rounded shadow-sm hover:bg-green-700 font-bold text-sm disabled:opacity-50">Approve</button>
                            @foreach($branches as $i => $branch)
                            <button wire:click="forwardToBranch({{ $i }})" wire:loading.attr="disabled"
                                class="bg-indigo-600 text-white px-4 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm disabled:opacity-50">
                                → {{ $branch['label'] }}
                            </button>
                            @endforeach
                            @if($module->has_return_button && ($currentStage?->has_return_button ?? true))
                            <button wire:click="returnForRevision" wire:loading.attr="disabled"
                                class="bg-orange-500 text-white px-4 py-2 rounded shadow-sm hover:bg-orange-600 font-bold text-sm disabled:opacity-50">Return for Revision</button>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

            @if(!$canAct)
                <p class="text-sm text-gray-500 italic">
                    @if($record->status === 'Completed')
                        This record has been completed.
                    @elseif(in_array($record->status, ['Submitted','Under Review']))
                        This record is pending approval.
                    @else
                        No approval actions available.
                    @endif
                </p>
            @endif
        </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <form wire:submit="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">


                    @foreach($module->fields as $field)
                        @php
                            $vc = $field->visibility_conditions;
                            $hasVc = !empty($vc['field']);
                            $colSpanClass = ($field->col_span ?? 1) == 2 ? 'md:col-span-2' : '';
                        @endphp
                        <div class="{{ $colSpanClass }}" @if($hasVc)
                            x-data
                            x-show="(function(){
                                var f='{{ $vc['field'] ?? '' }}';
                                var op='{{ $vc['operator'] ?? '=' }}';
                                var exp='{{ $vc['value'] ?? '' }}';
                                var el = document.querySelector('[wire\\:model=\"data.'+f+'\"]') || document.querySelector('[wire\\:model\\:defer=\"data.'+f+'\"]');
                                var val = el ? el.value : '';
                                if(op==='=') return val===exp;
                                if(op==='!=') return val!==exp;
                                if(op==='contains') return val.includes(exp);
                                if(op==='not_empty') return val!=='';
                                return true;
                            })()"
                            x-init="$watch('$wire.data', () => { $el.__x && $el.__x.$data })"
                        @endif>
                            <label class="block text-sm font-medium text-gray-700">{{ $field->name }} @if($field->is_required) <span class="text-red-500">*</span> @endif</label>
                            @if($field->description)
                                <p class="text-xs text-gray-400 mb-1">{{ $field->description }}</p>
                            @endif
                            
                            @if($field->type === 'text')
                                <input type="text" wire:model="data.{{ $field->slug }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @elseif($field->type === 'textarea')
                                <textarea wire:model="data.{{ $field->slug }}" rows="4" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                            @elseif($field->type === 'email')
                                <input type="email" wire:model="data.{{ $field->slug }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @elseif($field->type === 'phone')
                                <input type="tel" wire:model="data.{{ $field->slug }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @elseif($field->type === 'url')
                                <input type="url" wire:model="data.{{ $field->slug }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @elseif($field->type === 'number')
                                <input type="number" wire:model="data.{{ $field->slug }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @elseif($field->type === 'currency')
                                <div class="relative mt-1">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 text-sm pointer-events-none">$</span>
                                    <input type="number" step="0.01" min="0" wire:model="data.{{ $field->slug }}" class="pl-7 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            @elseif($field->type === 'date')
                                <input type="date" wire:model="data.{{ $field->slug }}" min="{{ date('Y-m-d') }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @elseif($field->type === 'boolean')
                                <input type="checkbox" wire:model="data.{{ $field->slug }}" class="mt-1 rounded text-indigo-600 shadow-sm">
                            @elseif($field->type === 'select')
                                @php $options = is_array($field->options_json) ? $field->options_json : []; @endphp
                                <select wire:model="data.{{ $field->slug }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select an option</option>
                                    @foreach($options as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @elseif($field->type === 'multi_select')
                                @php $msOptions = is_array($field->options_json) ? $field->options_json : []; @endphp
                                <div class="mt-1 space-y-1">
                                    @foreach($msOptions as $opt)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" wire:model="data.{{ $field->slug }}" value="{{ $opt }}" class="rounded border-gray-300 text-indigo-600">
                                            {{ $opt }}
                                        </label>
                                    @endforeach
                                </div>
                            @elseif($field->type === 'user')
                                <select wire:model="data.{{ $field->slug }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Select User --</option>
                                    @foreach(\App\Models\User::orderBy('name')->get() as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            @elseif($field->type === 'text_editor')
                                @php
                                    $teOptions = $field->options_json ?? [];
                                    $teRequireReview = !empty($teOptions['require_review']);
                                    $teLogHistory    = !empty($teOptions['log_history']);
                                    $teTemplate      = $teOptions['template'] ?? '';
                                    $teRecordId      = $record?->id ?? 'new';
                                    $teUserColor     = '#' . substr(md5(auth()->id()), 0, 6);
                                    $teAlreadyReviewed = $teRequireReview && in_array($field->slug, $reviewedFields ?? []);
                                @endphp
                                {{-- Hidden input lives OUTSIDE wire:ignore so Livewire can bind to it --}}
                                <input
                                    type="hidden"
                                    wire:model="data.{{ $field->slug }}"
                                    id="te-input-{{ $field->slug }}"
                                >
                                <div
                                    class="text-editor-mount"
                                    data-record="{{ $teRecordId }}"
                                    data-field="{{ $field->slug }}"
                                    data-token="{{ $editorTokens[$field->slug] ?? throw new \RuntimeException('Editor token missing for field: ' . $field->slug) }}"
                                    data-template="{{ htmlspecialchars(json_encode($teTemplate), ENT_QUOTES, 'UTF-8') }}"
                                    data-content="{{ htmlspecialchars(json_encode($data[$field->slug] ?? ''), ENT_QUOTES, 'UTF-8') }}"
                                    data-require-review="{{ $teRequireReview ? '1' : '0' }}"
                                    data-log-history="{{ $teLogHistory ? '1' : '0' }}"
                                    data-user-name="{{ auth()->user()->name }}"
                                    data-user-color="{{ $teUserColor }}"
                                    data-readonly="{{ $teAlreadyReviewed ? '1' : '0' }}"
                                    wire:ignore
                                ></div>
                                <script>
                                (function(){var el=document.currentScript.previousElementSibling;el._teContent=@json($data[$field->slug] ?? '');el._teTemplate=@json($teTemplate);}())
                                </script>
                                @if($teRequireReview && $teRecordId !== 'new')
                                    @php
                                        $teReviewers = isset($reviewersByField[$field->slug])
                                            ? $reviewersByField[$field->slug]->pluck('name')->toArray()
                                            : [];
                                    @endphp
                                    <div class="mt-3 flex flex-wrap items-center gap-3">
                                        @if($canReview)
                                            @if($teAlreadyReviewed)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded text-xs font-semibold bg-green-100 text-green-700">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    Review Done
                                                </span>
                                            @else
                                                <button
                                                    wire:click="markReviewDone('{{ $field->slug }}')"
                                                    wire:loading.attr="disabled"
                                                    class="te-review-done-btn"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    Mark Review Done
                                                </button>
                                            @endif
                                        @endif
                                        @if(!empty($teReviewers))
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="text-xs text-gray-400">Reviewed by:</span>
                                                @foreach($teReviewers as $rName)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                        {{ $rName }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @elseif($field->type === 'attachment')
                                @php
                                    $versionedVal = $recordId ? ($record->data[$field->slug] ?? null) : null;
                                    $isVersioned = $field->versioning;
                                @endphp
                                <div x-data="{ progress: 0, uploading: false, sizeError: false }">
                                    <input type="file"
                                        x-on:change="
                                            const f = $event.target.files[0];
                                            if (!f) return;
                                            if (f.size > 52428800) {
                                                sizeError = true;
                                                $event.target.value = '';
                                                return;
                                            }
                                            sizeError = false;
                                            uploading = true;
                                            progress = 0;
                                            $wire.upload(
                                                'data.{{ $field->slug }}',
                                                f,
                                                () => { uploading = false; },
                                                () => { uploading = false; },
                                                (e) => { progress = e.detail.progress; }
                                            );
                                        "
                                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                    <p x-show="sizeError" x-cloak class="mt-1 text-xs text-red-600 font-medium">File exceeds the 50 MB limit.</p>
                                    <p x-show="!sizeError" class="mt-1 text-xs text-gray-400">Max 50 MB</p>
                                    <div x-show="uploading" x-cloak class="mt-2">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs text-gray-500">Uploading...</span>
                                            <span class="text-xs font-semibold text-indigo-600" x-text="progress + '%'"></span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                                            <div class="bg-indigo-500 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                                        </div>
                                    </div>
                                </div>

                                @if($isVersioned)
                                    @php $versions = is_array($versionedVal) ? $versionedVal : (is_string($versionedVal) && !empty($versionedVal) ? [['path' => $versionedVal, 'original_name' => basename($versionedVal), 'uploaded_at' => null, 'uploaded_by_name' => null]] : []); @endphp
                                    @if(count($versions) > 0)
                                        <div class="mt-3 border border-blue-100 rounded-md overflow-hidden">
                                            <div class="bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 flex items-center justify-between">
                                                <span>Version History</span>
                                                <span class="text-blue-500">{{ count($versions) }} {{ Str::plural('version', count($versions)) }}</span>
                                            </div>
                                            <div class="divide-y divide-gray-100">
                                                @foreach($versions as $vi => $ver)
                                                    <div class="flex items-center gap-3 px-3 py-2 {{ $vi === 0 ? 'bg-white' : 'bg-gray-50' }}">
                                                        <span class="text-[10px] font-bold text-gray-400 w-6 text-right flex-shrink-0">v{{ count($versions) - $vi }}</span>
                                                        <a href="{{ Storage::url($ver['path']) }}" target="_blank"
                                                            class="text-xs text-indigo-600 hover:underline truncate flex-1">
                                                            {{ $ver['original_name'] ?? basename($ver['path']) }}
                                                        </a>
                                                        @if($vi === 0)
                                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-green-100 text-green-700 flex-shrink-0">Latest</span>
                                                        @endif
                                                        @if(!empty($ver['uploaded_at']))
                                                            <span class="text-[10px] text-gray-400 flex-shrink-0">{{ \Carbon\Carbon::parse($ver['uploaded_at'])->diffForHumans() }}</span>
                                                        @endif
                                                        @if(!empty($ver['uploaded_by_name']))
                                                            <span class="text-[10px] text-gray-500 flex-shrink-0">{{ $ver['uploaded_by_name'] }}</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @elseif(isset($data[$field->slug]) && is_string($data[$field->slug]) && !empty($data[$field->slug]) && !is_object($data[$field->slug]))
                                    <div class="mt-2 text-sm"><a href="{{ Storage::url($data[$field->slug]) }}" target="_blank" class="text-indigo-600 hover:underline">{{ basename($data[$field->slug]) }}</a></div>
                                @endif
                            @endif
                            
                            @error('data.' . $field->slug) <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <a href="{{ route('dynamic.index', $moduleSlug) }}" wire:navigate class="bg-gray-200 text-gray-800 px-4 py-2 rounded shadow-sm hover:bg-gray-300 font-bold text-sm flex items-center">Cancel</a>
                    @if(!$recordId || in_array($record->status ?? '', ['Draft','Returned']))
                        @if($module->has_draft_button)
                            <button type="button" wire:click="saveAsDraft" wire:loading.attr="disabled"
                                class="bg-gray-500 text-white px-6 py-2 rounded shadow-sm hover:bg-gray-600 font-bold text-sm disabled:opacity-50">Save as Draft</button>
                        @endif
                        @if($canSubmit)
                            <button type="button" wire:click="saveAndSubmit" wire:loading.attr="disabled"
                                wire:confirm="Submit this record for approval?"
                                class="bg-indigo-600 text-white px-6 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm disabled:opacity-50">Submit</button>
                        @elseif(!$module->has_submit_button && !$module->has_draft_button)
                            <button type="submit" wire:loading.attr="disabled"
                                class="bg-indigo-600 text-white px-6 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm disabled:opacity-50">Save Record</button>
                        @endif
                    @endif
                </div>
            </form>
        </div>

    </div>{{-- end left column --}}

    {{-- RIGHT COLUMN: Approval Log, Comments, Activity History --}}
    @if($recordId)
    <div class="w-80 flex-shrink-0 space-y-6">

        {{-- Approval Log --}}
        @if($approvals->isNotEmpty())
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-base font-bold text-gray-800 border-b pb-2 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Approval Log
            </h3>
            <div class="space-y-2">
                @foreach($approvals as $ap)
                    <div class="flex items-start gap-3 text-sm">
                        <span class="flex-shrink-0 w-20 text-right text-xs font-bold uppercase
                            {{ $ap->action === 'approved' ? 'text-green-600' : '' }}
                            {{ $ap->action === 'returned' ? 'text-orange-500' : '' }}
                            {{ $ap->action === 'submitted' ? 'text-blue-600' : '' }}
                        ">{{ ucfirst($ap->action) }}</span>
                        <div class="flex-1">
                            <span class="font-medium">{{ $ap->user?->name ?? 'System' }}</span>
                            @if($ap->stage) <span class="text-gray-400 text-xs">— {{ $ap->stage->name }}</span> @endif
                            @if($ap->comment) <p class="text-gray-600 text-xs mt-0.5 italic">"{{ $ap->comment }}"</p> @endif
                        </div>
                        <span class="text-xs text-gray-400 flex-shrink-0">{{ $ap->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Comments --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
                Comments ({{ $comments->count() }})
            </h3>

            {{-- Existing comments --}}
            <div class="space-y-4 mb-6 max-h-96 overflow-y-auto">
                @forelse($comments as $comment)
                    <div class="flex gap-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold uppercase">
                            {{ substr($comment->user->name ?? 'U', 0, 1) }}
                        </div>
                        <div class="flex-1 bg-gray-50 rounded-lg p-3 border">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-semibold text-gray-800">{{ $comment->user->name ?? 'Unknown' }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                                    @if($canDeleteComments)
                                        <button wire:click="deleteComment({{ $comment->id }})" wire:confirm="Delete this comment permanently?" class="text-red-400 hover:text-red-600 transition" title="Delete comment">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $comment->body }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic text-center py-4">No comments yet. Be the first to add one.</p>
                @endforelse
            </div>

            {{-- Add comment form --}}
            <form wire:submit="addComment" class="border-t pt-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-2">Add a Comment</label>
                <textarea wire:model="newComment" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Type your comment here..."></textarea>
                @error('newComment') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm transition">
                        Post Comment
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-2">Comments are permanent and cannot be deleted after posting.</p>
            </form>
        </div>

        {{-- Record History Timeline --}}
        @if($canDeleteComments && $histories->isNotEmpty())
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h3 class="text-base font-bold text-gray-800 border-b pb-2 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Activity History
            </h3>
            <ol class="relative border-l border-gray-200 space-y-4 ml-3">
                @foreach($histories as $h)
                <li class="ml-4">
                    <div class="absolute w-2.5 h-2.5 bg-indigo-400 rounded-full mt-1.5 -left-1.5 border border-white"></div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase
                            {{ $h->action === 'created' ? 'text-green-600' : '' }}
                            {{ $h->action === 'updated' ? 'text-blue-600' : '' }}
                            {{ $h->action === 'approved' ? 'text-green-700' : '' }}
                            {{ $h->action === 'returned' ? 'text-orange-500' : '' }}
                            {{ $h->action === 'submitted' ? 'text-indigo-600' : '' }}
                        ">{{ ucfirst($h->action) }}</span>
                        <span class="text-sm text-gray-600">by <strong>{{ $h->user?->name ?? 'System' }}</strong></span>
                        <span class="text-xs text-gray-400 ml-auto">{{ $h->created_at->diffForHumans() }}</span>
                    </div>
                    @if(!empty($h->changes_json['comment']))
                        <p class="text-xs text-gray-500 mt-1 italic">"{{ $h->changes_json['comment'] }}"</p>
                    @endif
                </li>
                @endforeach
            </ol>
        </div>
        @endif

    </div>{{-- end right column --}}
    @endif

    </div>{{-- end flex --}}
    </div>{{-- end max-w-7xl --}}
</div>
