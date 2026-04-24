<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Module;
use App\Models\Record;
use App\Models\RecordComment;
use App\Models\RecordHistory;
use App\Models\RecordApproval;
use App\Models\WorkflowStage;
use App\Models\User;
use App\Notifications\DynamicNotification;
use Spatie\Permission\Models\Role;
use Livewire\Attributes\Layout;

class DynamicRecordShow extends Component
{
    use WithFileUploads;
    public $moduleSlug;
    public $module;
    public $record;
    public $recordId;
    public $approvalComment = '';
    public $newComment = '';
    public $reviewerAttachment = null;
    public $stageFieldValues = [];
    protected array $editorTokens = []; // Minted in mount(); passed via render() — not exposed to Livewire snapshot

    public function mount($moduleSlug, $record)
    {
        $this->moduleSlug = $moduleSlug;
        $this->module = Module::with('fields')->where('slug', $moduleSlug)->firstOrFail();

        $canView      = auth()->user()->can("view-{$this->moduleSlug}");
        $canApprove   = auth()->user()->can("approve-{$this->moduleSlug}");
        $canReview    = auth()->user()->can("review-{$this->moduleSlug}");

        // Also allow if the user's role is designated as an approver for any stage in this module
        // Gate::before in AppServiceProvider gives super admin all can() checks automatically
        $stageRoleIds   = WorkflowStage::where('module_id', $this->module->id)->pluck('approver_role_id')->filter();
        $isStageApprover = auth()->user()->roles->pluck('id')->intersect($stageRoleIds)->isNotEmpty();

        if (!$canView && !$canApprove && !$canReview && !$isStageApprover) abort(403);

        $targetModuleId = $this->module->source_module_id ?? $this->module->id;

        if ($this->module->source_module_id) {
            $sourceFields = Module::find($this->module->source_module_id)->fields;
            $this->module->setRelation('fields', $sourceFields->merge($this->module->fields));
        }

        $this->record = Record::with('currentStage')->where('module_id', $targetModuleId)->findOrFail($record);
        $this->recordId = $this->record->id;

        // Pre-fill stage field values from existing record data
        foreach ($this->record->currentStage?->stage_fields_json ?? [] as $sf) {
            $slug = is_array($sf) ? ($sf['slug'] ?? '') : $sf;
            if ($slug) $this->stageFieldValues[$slug] = $this->record->data[$slug] ?? '';
        }

        // Mint one editor token per text_editor field — reused on every re-render.
        // Scoped to recordId so form and show components cannot stomp each other's tokens.
        // Revoke ALL previous tokens for this prefix immediately to prevent accumulation on repeated views.
        $tokenPrefix = 'editor-' . $this->recordId . '-';
        auth()->user()->tokens()->where('name', 'like', $tokenPrefix . '%')->delete();
        auth()->user()->tokens()->where('name', 'like', 'editor-%')->whereNotLike('name', 'editor-%-%-%')->where('created_at', '<', now()->subHours(8))->delete();
        foreach ($this->module->fields as $field) {
            if ($field->type === 'text_editor') {
                $this->editorTokens[$field->slug] = auth()->user()
                    ->createToken($tokenPrefix . $field->slug, ['editor:read', 'editor:write'], now()->addHours(8))
                    ->plainTextToken;
            }
        }
    }

    public function approve()
    {
        $this->authorizeApprovalAction();
        if (!$this->validateRequiredStageFields()) return;

        $currentStage = $this->record->currentStage;

        RecordApproval::create([
            'record_id' => $this->record->id,
            'stage_id'  => $currentStage?->id,
            'user_id'   => auth()->id(),
            'action'    => 'approved',
            'comment'   => $this->approvalComment ?: null,
        ]);

        RecordHistory::create([
            'record_id'    => $this->record->id,
            'user_id'      => auth()->id(),
            'action'       => 'approved',
            'changes_json' => $this->approvalComment ? ['comment' => $this->approvalComment] : null,
        ]);

        $targetModuleId = $this->module->source_module_id ?? $this->module->id;
        $isFinal = !$currentStage || $currentStage->is_final_approval;

        if (!$isFinal) {
            $nextStage = WorkflowStage::where('module_id', $targetModuleId)
                ->where('order', '>', $currentStage->order)
                ->orderBy('order')
                ->first();

            if ($nextStage) {
                $this->record->update(['status' => $nextStage->default_status ?? 'Under Review', 'current_stage_id' => $nextStage->id, 'stage_entered_at' => now()]);
                $this->notifyStageApprovers($nextStage, "A record in {$this->module->name} has advanced and requires your approval.");
                $this->approvalComment = '';
                $this->dispatch('notify', type: 'success', message: 'Approved. Record advanced to next stage.');
                return;
            }
        }

        $this->record->update(['status' => 'Completed', 'current_stage_id' => null]);
        $this->approvalComment = '';

        $submitter = User::find($this->record->created_by);
        $submitter?->notify(new DynamicNotification("Your record in {$this->module->name} has been completed.", $this->record->id, $this->moduleSlug));

        $this->dispatch('notify', type: 'success', message: 'Record approved successfully.');
    }

    public function forwardToBranch($index)
    {
        $this->authorizeApprovalAction();
        if (!$this->validateRequiredStageFields()) return;

        $currentStage = $this->record->currentStage;
        $branches = $currentStage?->branches_json ?? [];
        $branch = $branches[$index] ?? null;

        if (!$branch || empty($branch['stage_id'])) {
            $this->dispatch('notify', type: 'error', message: 'Invalid branch.');
            return;
        }

        $targetStage = WorkflowStage::find($branch['stage_id']);
        $label = $branch['label'];

        RecordApproval::create([
            'record_id' => $this->record->id,
            'stage_id'  => $currentStage?->id,
            'user_id'   => auth()->id(),
            'action'    => 'forwarded',
            'comment'   => $this->approvalComment ?: null,
        ]);

        RecordHistory::create([
            'record_id'    => $this->record->id,
            'user_id'      => auth()->id(),
            'action'       => 'forwarded',
            'changes_json' => ['path' => $label, 'to_stage' => $targetStage?->name],
        ]);

        $this->record->update([
            'status'           => $targetStage?->default_status ?? 'Under Review',
            'current_stage_id' => $targetStage?->id,
            'stage_entered_at' => now(),
        ]);

        if ($targetStage) {
            $this->notifyStageApprovers($targetStage, "A record in {$this->module->name} has been forwarded ({$label}) and requires your action.");
        }

        $this->approvalComment = '';
        $this->dispatch('notify', type: 'success', message: "Record forwarded: {$label}.");
    }

    public function returnForRevision()
    {
        $this->authorizeApprovalAction();
        $this->validate(['approvalComment' => 'required|string|max:2000'], [], ['approvalComment' => 'revision notes']);

        RecordApproval::create([
            'record_id' => $this->record->id,
            'stage_id'  => $this->record->current_stage_id,
            'user_id'   => auth()->id(),
            'action'    => 'returned',
            'comment'   => $this->approvalComment,
        ]);

        RecordHistory::create([
            'record_id'    => $this->record->id,
            'user_id'      => auth()->id(),
            'action'       => 'returned',
            'changes_json' => ['comment' => $this->approvalComment],
        ]);

        $this->record->update(['status' => 'Returned', 'current_stage_id' => null]);
        $this->approvalComment = '';

        $submitter = User::find($this->record->created_by);
        $submitter?->notify(new DynamicNotification("Your record in {$this->module->name} has been returned for revision.", $this->record->id, $this->moduleSlug));

        $this->dispatch('notify', type: 'success', message: 'Record returned for revision.');
    }

    public function addComment()
    {
        $this->validate(['newComment' => 'required|string|max:2000']);

        RecordComment::create([
            'record_id' => $this->recordId,
            'user_id'   => auth()->id(),
            'body'      => $this->newComment,
        ]);

        $this->newComment = '';
    }

    public function saveStageFieldValues()
    {
        $this->authorizeApprovalAction();
        $stageFields = $this->record->currentStage?->stage_fields_json ?? [];
        if (empty($stageFields)) return;

        // Validate that required stage fields are non-empty before saving
        foreach ($stageFields as $sf) {
            if (!is_array($sf) || empty($sf['slug']) || empty($sf['is_required'])) continue;
            if (!array_key_exists($sf['slug'], $this->stageFieldValues)) continue;

            $val = $this->stageFieldValues[$sf['slug']];
            if ($val === null || $val === '') {
                $label = $sf['label'] ?? $sf['slug'];
                $this->dispatch('notify', type: 'error', message: "'{$label}' is required before saving.");
                return;
            }
        }

        $data = $this->record->data ?? [];
        foreach ($stageFields as $sf) {
            $slug = is_array($sf) ? ($sf['slug'] ?? '') : $sf;
            if ($slug && array_key_exists($slug, $this->stageFieldValues)) {
                $data[$slug] = $this->stageFieldValues[$slug];
            }
        }
        $this->record->update(['data' => $data]);

        RecordHistory::create([
            'record_id'    => $this->record->id,
            'user_id'      => auth()->id(),
            'action'       => 'updated stage fields',
            'changes_json' => ['fields' => array_column($stageFields, 'slug')],
        ]);

        $this->dispatch('notify', type: 'success', message: 'Stage fields saved.');
    }

    public function attachStageFile($fieldSlug)
    {
        abort_if(empty($fieldSlug), 422);
        $this->authorizeApprovalAction();
        if (!$this->reviewerAttachment) return;

        // Validate slug against current stage fields to prevent arbitrary key injection
        $allowedSlugs = collect($this->record->currentStage?->stage_fields_json ?? [])
            ->pluck('slug')->flip();
        if (!isset($allowedSlugs[$fieldSlug])) abort(422);

        $this->validate(['reviewerAttachment' => 'file|max:20480|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip|extensions:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip']);

        $path = $this->reviewerAttachment->store('attachments', 'public');
        $data = $this->record->data;
        $data[$fieldSlug] = $path;
        $this->record->update(['data' => $data]);

        // Keep stageFieldValues in sync so validation passes in the same request
        $this->stageFieldValues[$fieldSlug] = $path;

        RecordHistory::create([
            'record_id'    => $this->record->id,
            'user_id'      => auth()->id(),
            'action'       => 'attached file',
            'changes_json' => ['field' => $fieldSlug, 'path' => $path],
        ]);

        $this->reviewerAttachment = null;
        $this->dispatch('notify', type: 'success', message: 'File attached.');
    }

    public function markReviewDone(string $fieldSlug): void
    {
        if (! $this->canReview()) abort(403);

        \DB::table('text_editor_reviews')->updateOrInsert(
            [
                'record_id'  => $this->record->id,
                'field_slug' => $fieldSlug,
                'user_id'    => auth()->id(),
            ],
            [
                'reviewed_at' => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        // Count users in the current stage's approver role only
        $reviewerCount = 0;
        $stage = $this->record->currentStage;
        if ($stage?->approver_role_id) {
            $role = Role::find($stage->approver_role_id);
            if ($role) $reviewerCount = User::role($role->name)->count();
        }

        $doneCount = \DB::table('text_editor_reviews')
            ->where('record_id', $this->record->id)
            ->where('field_slug', $fieldSlug)
            ->count();

        if ($reviewerCount > 0 && $doneCount >= $reviewerCount) {
            $this->approve();
            return;
        }

        $this->dispatch('review-marked-done', fieldSlug: $fieldSlug);
        $this->dispatch('notify', type: 'success', message: 'Review marked as done.');
    }

    public function deleteComment($commentId)
    {
        if (!auth()->user()->can('delete-comments')) abort(403);
        RecordComment::where('id', $commentId)->where('record_id', $this->recordId)->delete();
    }

    private function validateRequiredStageFields(): bool
    {
        $stageFields = $this->record->currentStage?->stage_fields_json ?? [];
        $missing = [];

        foreach ($stageFields as $sf) {
            if (!is_array($sf) || empty($sf['slug']) || empty($sf['is_required'])) continue;
            // Prefer record->data (authoritative, includes saved attachments) over
            // stageFieldValues which may be a stale empty string from before a file upload.
            $dataValue = $this->record->data[$sf['slug']] ?? null;
            $value = ($dataValue !== null && $dataValue !== '' && $dataValue !== [])
                ? $dataValue
                : ($this->stageFieldValues[$sf['slug']] ?? null);
            if ($value === null || $value === '' || $value === []) {
                $missing[] = $sf['label'] ?? $sf['slug'];
            }
        }

        if (!empty($missing)) {
            $this->dispatch('notify', type: 'error', message: 'Required stage fields must be filled before proceeding: ' . implode(', ', $missing) . '.');
            return false;
        }

        return true;
    }

    private function authorizeApprovalAction(): void
    {
        $stage = $this->record->currentStage;

        // Designated role for this stage is sufficient
        if ($stage && $stage->approver_role_id) {
            $role = Role::find($stage->approver_role_id);
            if ($role && auth()->user()->hasRole($role->name)) return;
        }

        // Direct approve permission is also sufficient
        if (auth()->user()->can("approve-{$this->moduleSlug}")) return;

        abort(403);
    }

    private function canAct(): bool
    {
        if (!$this->record || !$this->record->current_stage_id) return false;

        // Super admin can always act — hasRole() below bypasses Gate::before, so guard explicitly
        if (auth()->user()->hasRole('super admin')) return true;

        $stage = $this->record->currentStage;

        // If a specific role is designated, only that role can act — general permission does not override
        if ($stage && $stage->approver_role_id) {
            $role = Role::find($stage->approver_role_id);
            return $role && auth()->user()->hasRole($role->name);
        }

        // No specific role assigned — fall back to general approve permission
        return auth()->user()->can("approve-{$this->moduleSlug}");
    }

    private function canReview(): bool
    {
        $user = auth()->user();
        if ($user->hasRole('super admin')) return false;
        return $user->can("review-{$this->moduleSlug}");
    }

    private function notifyStageApprovers(WorkflowStage $stage, string $message): void
    {
        if (!$stage->approver_role_id) return;
        $role = Role::find($stage->approver_role_id);
        if (!$role) return;
        foreach (User::role($role->name)->get() as $approver) {
            $approver->notify(new DynamicNotification($message, $this->record->id, $this->moduleSlug));
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $this->module = Module::with('fields')->find($this->module->id);
        if ($this->module->source_module_id) {
            $sourceFields = Module::find($this->module->source_module_id)->fields;
            $this->module->setRelation('fields', $sourceFields->merge($this->module->fields));
        }

        $this->record = Record::with('currentStage')->find($this->recordId);

        $usersMap     = User::pluck('name', 'id');
        $comments     = RecordComment::where('record_id', $this->recordId)->with('user')->oldest()->get();
        $histories    = RecordHistory::where('record_id', $this->recordId)->with('user')->latest()->get();
        $approvals    = RecordApproval::where('record_id', $this->recordId)->with(['user', 'stage'])->latest()->get();
        $canAct       = $this->canAct();
        $currentStage = $this->record->currentStage;

        // Build ordered stage field groups — current and past only (future hidden)
        $targetModuleId = $this->module->source_module_id ?? $this->module->id;
        $user         = auth()->user();
        $stageAllowsEdit = $currentStage === null || ($currentStage->allow_edit ?? true);
        // Gate::before gives super admin all can() checks — no explicit hasRole needed
        $canEdit      = $stageAllowsEdit && $user->can("edit-{$this->moduleSlug}");
        $canDeleteComments = $user->can('delete-comments');
        $allStages = WorkflowStage::where('module_id', $targetModuleId)->orderBy('order')->get();
        $currentOrder = $currentStage?->order ?? PHP_INT_MAX;
        $hasCurrentStage = $this->record->current_stage_id !== null;

        $stageFieldGroups = [];
        foreach ($allStages as $stage) {
            $defs = array_values(array_filter(
                $stage->stage_fields_json ?? [],
                fn($sf) => is_array($sf) && !empty($sf['slug'])
            ));
            if (empty($defs)) continue;
            // Hide future stages while record is still in workflow
            if ($hasCurrentStage && $stage->order > $currentOrder) continue;
            $stageFieldGroups[] = [
                'stage'      => $stage,
                'defs'       => $defs,
                'is_current' => $stage->id === $this->record->current_stage_id,
            ];
        }

        $canReview = $this->canReview();
        $reviewedFields = \DB::table('text_editor_reviews')
            ->where('record_id', $this->recordId)
            ->where('user_id', auth()->id())
            ->pluck('field_slug')->all();

        $reviewersByField = \DB::table('text_editor_reviews')
            ->join('users', 'text_editor_reviews.user_id', '=', 'users.id')
            ->where('text_editor_reviews.record_id', $this->recordId)
            ->orderBy('text_editor_reviews.reviewed_at')
            ->select('text_editor_reviews.field_slug', 'users.name')
            ->get()
            ->groupBy('field_slug');

        // Re-mint editor tokens if lost after Livewire re-hydration (protected property not in snapshot)
        if (empty($this->editorTokens)) {
            $tokenPrefix = 'editor-' . $this->recordId . '-';
            foreach ($this->module->fields as $field) {
                if ($field->type === 'text_editor') {
                    $this->editorTokens[$field->slug] = auth()->user()
                        ->createToken($tokenPrefix . $field->slug, ['editor:read', 'editor:write'], now()->addHours(8))
                        ->plainTextToken;
                }
            }
        }
        $editorTokens = $this->editorTokens;

        return view('livewire.builder.dynamic-record-show',
            compact('usersMap', 'comments', 'histories', 'approvals', 'canAct', 'canEdit', 'canDeleteComments', 'currentStage', 'stageFieldGroups', 'canReview', 'reviewedFields', 'reviewersByField', 'editorTokens'));
    }
}
