<x-slot name="header">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                View {{ $module->name }}
            </h2>
            @if($currentStage)
                <span class="px-3 py-1 rounded-full text-sm font-bold bg-indigo-600 text-white shadow-sm">
                    {{ $currentStage->name }}
                </span>
            @elseif($record->status === 'Returned')
                <span class="px-3 py-1 rounded-full text-sm font-bold bg-orange-100 text-orange-800 shadow-sm">
                    Returned
                </span>
            @elseif($record->status === 'Draft')
                <span class="px-3 py-1 rounded-full text-sm font-bold bg-yellow-100 text-yellow-800 shadow-sm">
                    Draft
                </span>
            @elseif($record->status === 'Archived')
                <span class="px-3 py-1 rounded-full text-sm font-bold bg-gray-100 text-gray-600 shadow-sm">
                    Archived
                </span>
            @else
                <span class="px-3 py-1 rounded-full text-sm font-bold bg-green-100 text-green-800 shadow-sm">
                    Completed
                </span>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 rounded-full text-xs font-bold
                {{ $record->status === 'Completed' ? 'bg-green-100 text-green-800' : '' }}
                {{ $record->status === 'Submitted' ? 'bg-blue-100 text-blue-800' : '' }}
                {{ $record->status === 'Under Review' ? 'bg-indigo-100 text-indigo-800' : '' }}
                {{ $record->status === 'Returned' ? 'bg-orange-100 text-orange-800' : '' }}
                {{ $record->status === 'Draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                {{ $record->status === 'Archived' ? 'bg-gray-100 text-gray-600' : '' }}
                {{ !in_array($record->status, ['Completed','Submitted','Under Review','Returned','Draft','Archived']) ? 'bg-gray-100 text-gray-700' : '' }}
            ">{{ $record->status }}</span>
            @if($canEdit && $record->status !== 'Completed')
                <a href="{{ route('dynamic.edit', ['moduleSlug' => $moduleSlug, 'record' => $record->id]) }}" wire:navigate class="bg-indigo-600 text-white px-4 py-1.5 rounded shadow-sm text-sm font-bold hover:bg-indigo-700">Edit</a>
            @endif
            <a href="{{ route('dynamic.index', $moduleSlug) }}" wire:navigate class="bg-gray-200 text-gray-700 px-4 py-1.5 rounded shadow-sm text-sm font-bold hover:bg-gray-300">Back</a>
        </div>
    </div>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="flex gap-6 items-start">

    {{-- LEFT COLUMN --}}
    <div class="flex-1 min-w-0 space-y-6">

        {{-- Approval Actions Panel --}}
        @if($canAct)
        @php
            $stageType = $currentStage?->stage_type ?? 'approval';
            $isReviewStage = $stageType === 'review';
            $isNoneStage   = $stageType === 'none';
            $branches      = $currentStage?->branches_json ?? [];
        @endphp
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
            <h3 class="text-base font-bold text-gray-800 mb-1 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $isReviewStage ? 'Review Actions' : ($isNoneStage ? 'Routing Actions' : 'Approval Actions') }}
            </h3>
            @if($currentStage)
                <p class="text-xs text-gray-500 mb-4">
                    Current stage: <strong>{{ $currentStage->name }}</strong>
                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase
                        {{ $isReviewStage ? 'bg-blue-100 text-blue-700' : ($isNoneStage ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700') }}">
                        {{ $isReviewStage ? 'Reviewer' : ($isNoneStage ? 'Routing' : 'Approver') }}
                    </span>
                </p>
            @endif
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">
                        {{ ($isReviewStage || $isNoneStage) ? 'Comment (optional)' : 'Comment / Reason (required for Return)' }}
                    </label>
                    <textarea wire:model="approvalComment" rows="2" placeholder="Add a comment..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                    @error('approvalComment') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-3 flex-wrap">
                    @if($isNoneStage)
                        {{-- None: branch buttons only --}}
                        @foreach($branches as $i => $branch)
                        <button wire:click="forwardToBranch({{ $i }})" wire:loading.attr="disabled"
                            class="bg-indigo-600 text-white px-4 py-2 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm disabled:opacity-50">
                            → {{ $branch['label'] }}
                        </button>
                        @endforeach
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
        </div>
        @endif

        {{-- Field Values --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
            <div class="space-y-5">
                @foreach($module->fields as $field)
                    @php $val = $record->data[$field->slug] ?? null; @endphp
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $field->name }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($field->type === 'boolean')
                                <span class="px-2 py-0.5 rounded text-xs font-bold {{ $val ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $val ? 'Yes' : 'No' }}
                                </span>
                            @elseif($field->type === 'attachment' && is_array($val) && !empty($val))
                                <div class="border border-blue-100 rounded-md overflow-hidden inline-block min-w-48">
                                    <div class="bg-blue-50 px-3 py-1 text-[10px] font-bold text-blue-600 uppercase tracking-wide flex justify-between">
                                        <span>Attachments</span><span>{{ count($val) }} {{ Str::plural('version', count($val)) }}</span>
                                    </div>
                                    <div class="divide-y divide-gray-100">
                                        @foreach($val as $vi => $ver)
                                            <div class="flex items-center gap-2 px-3 py-1.5 {{ $vi === 0 ? 'bg-white' : 'bg-gray-50' }}">
                                                <span class="text-[10px] font-bold text-gray-400 w-5 text-right flex-shrink-0">v{{ count($val) - $vi }}</span>
                                                <a href="{{ Storage::url($ver['path']) }}" target="_blank"
                                                    class="text-xs text-indigo-600 hover:underline flex-1 truncate">
                                                    {{ $ver['original_name'] ?? basename($ver['path']) }}
                                                </a>
                                                @if($vi === 0)
                                                    <span class="text-[10px] font-bold px-1 py-0.5 rounded bg-green-100 text-green-700 flex-shrink-0">Latest</span>
                                                @endif
                                                @if(!empty($ver['uploaded_at']))
                                                    <span class="text-[10px] text-gray-400 flex-shrink-0">{{ \Carbon\Carbon::parse($ver['uploaded_at'])->diffForHumans() }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif($field->type === 'attachment' && !empty($val))
                                <a href="{{ Storage::url($val) }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:underline border border-indigo-200 bg-indigo-50 px-3 py-1 rounded text-xs font-medium">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    View Attachment
                                </a>
                            @elseif($field->type === 'attachment')
                                <span class="text-gray-400 italic text-xs">No attachment</span>
                            @elseif($field->type === 'multi_select' && is_array($val))
                                @if(!empty($val))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($val as $item)
                                            <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded text-xs font-medium">{{ $item }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400 italic text-xs">None selected</span>
                                @endif
                            @elseif($field->type === 'user' && $val)
                                {{ $usersMap[$val] ?? 'User #'.$val }}
                            @elseif($field->type === 'currency' && $val !== null && $val !== '')
                                ${{ number_format((float)$val, 2) }}
                            @elseif($field->type === 'url' && !empty($val))
                                <a href="{{ $val }}" target="_blank" class="text-indigo-600 hover:underline break-all">{{ $val }}</a>
                            @elseif($field->type === 'text_editor')
                                @php
                                    $teOptions = $field->options_json ?? [];
                                    $teRequireReview = !empty($teOptions['require_review']);
                                    $teLogHistory    = !empty($teOptions['log_history']);
                                    $teTemplate      = $teOptions['template'] ?? '';
                                    $teUserColor     = '#' . substr(md5(auth()->id()), 0, 6);
                                    $teAlreadyReviewed = $teRequireReview && in_array($field->slug, $reviewedFields ?? []);
                                    // Editable if approver OR reviewer who hasn't yet reviewed; read-only after reviewing
                                    $teReadonly = ($teAlreadyReviewed || (!$canAct && !$canReview)) ? '1' : '0';
                                @endphp
                                <div
                                    class="text-editor-mount"
                                    data-record="{{ $record->id }}"
                                    data-field="{{ $field->slug }}"
                                    data-token="{{ $editorTokens[$field->slug] ?? throw new \RuntimeException('Editor token missing for field: ' . $field->slug) }}"
                                    data-content="{{ htmlspecialchars(json_encode($record->data[$field->slug] ?? ''), ENT_QUOTES, 'UTF-8') }}"
                                    data-template="{{ htmlspecialchars(json_encode($teTemplate), ENT_QUOTES, 'UTF-8') }}"
                                    data-require-review="{{ $teRequireReview ? '1' : '0' }}"
                                    data-log-history="{{ $teLogHistory ? '1' : '0' }}"
                                    data-user-name="{{ auth()->user()->name }}"
                                    data-user-color="{{ $teUserColor }}"
                                    data-readonly="{{ $teReadonly }}"
                                    wire:ignore
                                ></div>
                                <script>
                                (function(){var el=document.currentScript.previousElementSibling;el._teContent=@json($record->data[$field->slug] ?? '');el._teTemplate=@json($teTemplate);}())
                                </script>
                                @if($teRequireReview)
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
                            @elseif($field->type === 'textarea' && !empty($val))
                                <p class="whitespace-pre-wrap">{{ $val }}</p>
                            @elseif($field->type === 'text_editor')
                                {{-- text_editor is handled by the @elseif above; this is a safety fallback --}}
                            @else
                                {{ $val ?? '—' }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </div>

            {{-- Stage Fields inline — past (read-only) and current (editable if reviewer) --}}
            @foreach($stageFieldGroups as $group)
            @php
                $isEditable = $group['is_current'] && $canAct;
                $hasNonFileInGroup = collect($group['defs'])->filter(fn($sf) => ($sf['type'] ?? '') !== 'attachment')->isNotEmpty();
                // For past stages, skip the whole group if no fields have values yet
                $hasAnyValue = $isEditable || collect($group['defs'])->contains(fn($sf) => !empty($record->data[$sf['slug']] ?? null));
            @endphp
            @if(!$hasAnyValue) @continue @endif
            <div class="mt-5 pt-5 border-t">
                <p class="text-xs font-bold uppercase tracking-wide mb-3 flex items-center gap-2
                    {{ $isEditable ? 'text-teal-700' : 'text-gray-400' }}">
                    {{ $group['stage']->name }}
                    @if($isEditable)
                        <span class="px-1.5 py-0.5 bg-teal-100 text-teal-700 text-[10px] rounded font-bold">Current Stage — editable</span>
                    @endif
                </p>
                <div class="space-y-4">
                @foreach($group['defs'] as $sf)
                    @php $slug = $sf['slug']; $sfType = $sf['type'] ?? 'text'; $val = $record->data[$slug] ?? null; @endphp
                    @if(!$isEditable && ($val === null || $val === '' || $val === [])) @continue @endif
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            {{ $sf['name'] ?? $slug }}
                            @if(!empty($sf['is_required']) && $isEditable) <span class="text-red-400">*</span> @endif
                        </dt>
                        <dd class="mt-1">
                        @if($isEditable)
                            @if($sfType === 'attachment')
                                @if($val)
                                    <div class="mb-1 text-xs">
                                        <a href="{{ Storage::url($val) }}" target="_blank" class="text-indigo-600 hover:underline inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                            Current file
                                        </a>
                                        <span class="text-gray-400 ml-1">— upload to replace</span>
                                    </div>
                                @endif
                                <div class="flex items-center gap-2">
                                    <input type="file" wire:model="reviewerAttachment" class="block flex-1 text-sm text-gray-600 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700">
                                    <button wire:click="attachStageFile('{{ $slug }}')" wire:loading.attr="disabled"
                                        class="whitespace-nowrap bg-teal-600 text-white px-3 py-1.5 rounded hover:bg-teal-700 font-bold text-xs disabled:opacity-50">
                                        <span wire:loading.remove wire:target="attachStageFile('{{ $slug }}')">Attach</span>
                                        <span wire:loading wire:target="attachStageFile('{{ $slug }}')">…</span>
                                    </button>
                                </div>
                                @error('reviewerAttachment') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            @elseif($sfType === 'textarea')
                                <textarea wire:model="stageFieldValues.{{ $slug }}" rows="2"
                                    class="block w-full rounded border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm"></textarea>
                            @elseif($sfType === 'select')
                                <select wire:model="stageFieldValues.{{ $slug }}"
                                    class="block w-full rounded border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">
                                    <option value="">— select —</option>
                                    @foreach($sf['options_json'] ?? [] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @elseif($sfType === 'multi_select')
                                <div class="flex flex-wrap gap-2 mt-1">
                                @foreach($sf['options_json'] ?? [] as $opt)
                                    <label class="inline-flex items-center gap-1.5">
                                        <input type="checkbox" value="{{ $opt }}" wire:model="stageFieldValues.{{ $slug }}" class="rounded border-gray-300 text-teal-600">
                                        <span class="text-sm text-gray-700">{{ $opt }}</span>
                                    </label>
                                @endforeach
                                </div>
                            @elseif($sfType === 'boolean')
                                <label class="inline-flex items-center gap-2 mt-1">
                                    <input type="checkbox" wire:model="stageFieldValues.{{ $slug }}" class="rounded border-gray-300 text-teal-600">
                                    <span class="text-sm text-gray-600">Yes</span>
                                </label>
                            @elseif($sfType === 'date')
                                <input type="date" wire:model="stageFieldValues.{{ $slug }}" min="{{ date('Y-m-d') }}"
                                    class="block w-full rounded border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">
                            @else
                                <input type="{{ in_array($sfType, ['number','email','url']) ? $sfType : 'text' }}"
                                    wire:model="stageFieldValues.{{ $slug }}"
                                    class="block w-full rounded border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm">
                            @endif
                        @else
                            {{-- Read-only: past stage or non-reviewer --}}
                            @if($sfType === 'boolean')
                                <span class="px-2 py-0.5 rounded text-xs font-bold {{ $val ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">{{ $val ? 'Yes' : 'No' }}</span>
                            @elseif($sfType === 'attachment' && !empty($val))
                                <a href="{{ Storage::url($val) }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:underline border border-indigo-200 bg-indigo-50 px-3 py-1 rounded text-xs font-medium">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    View Attachment
                                </a>
                            @elseif($sfType === 'attachment')
                                <span class="text-gray-400 italic text-xs">No attachment</span>
                            @elseif($sfType === 'multi_select' && is_array($val) && !empty($val))
                                <div class="flex flex-wrap gap-1">@foreach($val as $item)<span class="px-2 py-0.5 bg-teal-50 text-teal-700 rounded text-xs font-medium">{{ $item }}</span>@endforeach</div>
                            @elseif($sfType === 'textarea' && !empty($val))
                                <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ $val }}</p>
                            @elseif($sfType === 'currency' && $val !== null && $val !== '')
                                <span class="text-sm text-gray-900">${{ number_format((float)$val, 2) }}</span>
                            @else
                                <span class="text-sm text-gray-900">{{ is_array($val) ? implode(', ', $val) : ($val ?? '—') }}</span>
                            @endif
                        @endif
                        </dd>
                    </div>
                @endforeach
                </div>
                @if($isEditable && $hasNonFileInGroup)
                <div class="mt-3">
                    <button wire:click="saveStageFieldValues" wire:loading.attr="disabled"
                        class="bg-teal-600 text-white px-4 py-1.5 rounded shadow-sm hover:bg-teal-700 font-bold text-sm disabled:opacity-50">
                        Save Fields
                    </button>
                </div>
                @endif
            </div>
            @endforeach

            <div class="mt-6 pt-4 border-t text-xs text-gray-400 flex gap-6">
                <span>Created by <strong>{{ $record->creator?->name ?? '—' }}</strong></span>
                <span>{{ $record->created_at->format('M d, Y H:i') }}</span>
                @if($record->updated_at != $record->created_at)
                    <span>Updated {{ $record->updated_at->diffForHumans() }}</span>
                @endif
            </div>
        </div>

    </div>{{-- end left column --}}

    {{-- RIGHT COLUMN --}}
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
                    @php
                        $isForwarded = $ap->action === 'approved' && $ap->stage?->stage_type === 'review';
                        $actionLabel = $isForwarded ? 'Forwarded' : ucfirst($ap->action);
                    @endphp
                    <div class="flex items-start gap-3 text-sm">
                        <span class="flex-shrink-0 w-20 text-right text-xs font-bold uppercase
                            {{ $ap->action === 'approved' && !$isForwarded ? 'text-green-600' : '' }}
                            {{ $isForwarded ? 'text-blue-600' : '' }}
                            {{ $ap->action === 'returned' ? 'text-orange-500' : '' }}
                            {{ $ap->action === 'submitted' ? 'text-indigo-600' : '' }}
                        ">{{ $actionLabel }}</span>
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
            <div class="space-y-4 max-h-96 overflow-y-auto mb-4">
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
                                        <button wire:click="deleteComment({{ $comment->id }})"
                                            wire:confirm="Delete this comment?"
                                            aria-label="Delete comment"
                                            class="text-red-400 hover:text-red-600 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $comment->body }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic text-center py-4">No comments yet.</p>
                @endforelse
            </div>
            <form wire:submit="addComment" class="border-t pt-4">
                <textarea wire:model="newComment" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Add a comment..."></textarea>
                @error('newComment') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                <div class="mt-2 flex justify-end">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-1.5 rounded shadow-sm hover:bg-indigo-700 font-bold text-sm">Post Comment</button>
                </div>
            </form>
        </div>

        {{-- Activity History --}}
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

    </div>{{-- end flex --}}
    </div>{{-- end max-w-7xl --}}
</div>
