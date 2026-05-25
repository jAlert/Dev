<?php

namespace App\Livewire\Builder;

use Livewire\Component;
use App\Models\Module;
use App\Models\Record;
use App\Models\RecordComment;
use App\Models\RecordHistory;
use App\Models\RecordApproval;
use App\Models\WorkflowStage;
use App\Models\User;
use App\Events\RecordSaved;
use App\Mail\StageNotificationMail;
use App\Notifications\DynamicNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;

class DynamicRecordForm extends Component
{
    use WithFileUploads;

    public $moduleSlug;
    public $module;
    public $recordId = null;
    public $record = null;
    public $data = [];
    public $status = '';
    public $newComment = '';

    // Approval
    public $approvalComment = '';
    public $showApprovalPanel = false;
    protected array $editorTokens = []; // Minted in mount(); passed via render() — not exposed to Livewire snapshot

    public function mount($moduleSlug, $record = null)
    {
        $this->moduleSlug = $moduleSlug;
        $this->module = Module::with('fields')->where('slug', $moduleSlug)->firstOrFail();

        $targetModuleId = $this->module->source_module_id ?? $this->module->id;

        if ($this->module->source_module_id) {
            $sourceFields = Module::find($this->module->source_module_id)->fields;
            $ownFields = $this->module->fields;
            $this->module->setRelation('fields', $sourceFields->merge($ownFields));
        }

        if ($record) {
            $this->record = Record::where('module_id', $targetModuleId)->findOrFail($record);
            if ($this->record->status === 'Completed') abort(403, 'Completed records cannot be edited.');
            if (!$this->canEditRecord()) abort(403);
            $this->recordId = $this->record->id;
            $this->data = $this->record->data ?? [];
            $this->status = $this->record->status ?? $this->module->default_status ?? 'Submitted';

            // Ensure multi_select fields are arrays; clear versioned attachment fields for file input
            foreach ($this->module->fields as $field) {
                if ($field->type === 'multi_select' && !is_array($this->data[$field->slug] ?? null)) {
                    $this->data[$field->slug] = [];
                } elseif ($field->type === 'attachment' && $field->versioning) {
                    // Keep the versions array in the record; clear the form binding so the file input works
                    $this->data[$field->slug] = '';
                }
            }
        } else {
            if (!auth()->user()->can("create-{$this->moduleSlug}")) abort(403);
            $this->status = $this->module->default_status ?? 'Submitted';

            foreach ($this->module->fields as $field) {
                $this->data[$field->slug] = match($field->type) {
                    'boolean'     => false,
                    'multi_select'=> [],
                    'text_editor' => $field->options_json['template'] ?? '',
                    default       => '',
                };
            }
        }

        // Mint one editor token per text_editor field — reused on every re-render.
        // Scoped to recordId (or 'new') so form and show components cannot stomp each other's tokens.
        $tokenPrefix = 'editor-' . ($this->recordId ?? 'new') . '-';
        // Revoke ALL previous tokens for this prefix immediately (not just old ones) to prevent accumulation.
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

    public function saveAsDraft()
    {
        $this->status = 'Draft';
        return $this->save();
    }

    public function saveAndSubmit()
    {
        $this->status = 'Draft';
        $record = $this->persistRecord();
        $this->record = $record;
        $this->recordId = $record->id;
        $this->submitForApproval();
        return redirect()->route('dynamic.index', $this->moduleSlug);
    }

    public function save()
    {
        $this->persistRecord();
        return redirect()->route('dynamic.index', $this->moduleSlug);
    }

    private function persistRecord(): Record
    {
        if ($this->record && $this->record->status === 'Completed') {
            abort(403, 'Completed records cannot be edited.');
        }

        $rules = ['status' => 'required|string'];

        foreach ($this->module->fields as $field) {
            if ($field->type === 'multi_select') {
                $rules['data.' . $field->slug] = $field->is_required ? 'required|array|min:1' : 'nullable|array';
            } elseif ($field->type === 'attachment' && $field->versioning) {
                // Required only when there are no existing versions yet
                $hasVersions = !empty($this->record?->data[$field->slug]);
                $rules['data.' . $field->slug] = ($field->is_required && !$hasVersions) ? 'required' : 'nullable';
            } else {
                $rules['data.' . $field->slug] = $field->is_required ? 'required' : 'nullable';
            }
        }

        $this->validate($rules);

        foreach ($this->module->fields as $field) {
            if ($field->type === 'attachment' && isset($this->data[$field->slug])) {
                $file = $this->data[$field->slug];
                if (is_object($file) && method_exists($file, 'store')) {
                    $this->validate([
                        'data.' . $field->slug => 'file|max:51200|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip|extensions:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,zip',
                    ]);
                    $path = $file->store('attachments', 'public');

                    if ($field->versioning) {
                        $existing = [];
                        if ($this->record) {
                            $existingVal = $this->record->data[$field->slug] ?? [];
                            if (is_string($existingVal) && !empty($existingVal)) {
                                // Migrate old single-file value into versioned array
                                $existing = [['path' => $existingVal, 'original_name' => basename($existingVal), 'uploaded_at' => null, 'uploaded_by' => null, 'uploaded_by_name' => 'Unknown']];
                            } elseif (is_array($existingVal)) {
                                $existing = $existingVal;
                            }
                        }
                        array_unshift($existing, [
                            'path'             => $path,
                            'original_name'    => $file->getClientOriginalName(),
                            'uploaded_at'      => now()->toDateTimeString(),
                            'uploaded_by'      => auth()->id(),
                            'uploaded_by_name' => auth()->user()->name,
                        ]);
                        $this->data[$field->slug] = $existing;
                    } else {
                        $this->data[$field->slug] = $path;
                    }
                } elseif ($field->versioning) {
                    // No new file uploaded — restore existing versions so they aren't wiped
                    $this->data[$field->slug] = $this->record ? ($this->record->data[$field->slug] ?? []) : [];
                }
            }
        }

        $isNew = !$this->recordId;
        $beforeData = $isNew ? null : ['data' => $this->record->data, 'status' => $this->record->status];

        $targetModuleId = $this->module->source_module_id ?? $this->module->id;

        $record = Record::updateOrCreate(
            ['id' => $this->recordId],
            [
                'module_id' => $targetModuleId,
                'data' => $this->data,
                'status' => $this->status,
                'created_by' => $this->recordId ? $this->record->created_by : auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        RecordHistory::create([
            'record_id' => $record->id,
            'user_id' => auth()->id(),
            'action' => $isNew ? 'created' : 'updated',
            'changes_json' => $isNew ? null : [
                'before' => $beforeData,
                'after' => ['data' => $this->data, 'status' => $this->status],
            ],
        ]);

        RecordSaved::dispatch($record, $isNew ? 'created' : 'updated');

        return $record;
    }

    // ─── Approval Actions ──────────────────────────────────────────────────────

    public function submitForApproval()
    {
        if (!$this->record) return;

        if (!in_array($this->record->status, ['Draft', 'Returned'])) {
            session()->flash('error', 'This record cannot be submitted in its current state.');
            return;
        }

        $targetModuleId = $this->module->source_module_id ?? $this->module->id;
        $firstStage = WorkflowStage::where('module_id', $targetModuleId)
            ->orderBy('order')
            ->first();

        if (!$firstStage) {
            session()->flash('error', 'No approval stages are configured for this module.');
            return;
        }

        $this->record->update([
            'status' => 'Submitted',
            'current_stage_id' => $firstStage->id,
            'stage_entered_at' => now(),
        ]);

        RecordApproval::create([
            'record_id' => $this->record->id,
            'stage_id' => $firstStage->id,
            'user_id' => auth()->id(),
            'action' => 'submitted',
        ]);

        RecordHistory::create([
            'record_id' => $this->record->id,
            'user_id' => auth()->id(),
            'action' => 'submitted',
        ]);

        $this->notifyStageUsers($firstStage, "A record in {$this->module->name} requires your approval.");

        $this->status = 'Submitted';
        session()->flash('message', 'Record submitted for approval.');
    }

    public function approve()
    {
        $this->authorizeApprovalAction();

        // Persist any in-progress edits (e.g. text editor changes) before advancing
        if ($this->record && $this->data) {
            $this->record->update(['data' => $this->data, 'updated_by' => auth()->id()]);
        }

        $currentStage = $this->record->currentStage;

        RecordApproval::create([
            'record_id' => $this->record->id,
            'stage_id' => $currentStage?->id,
            'user_id' => auth()->id(),
            'action' => 'approved',
            'comment' => $this->approvalComment ?: null,
        ]);

        RecordHistory::create([
            'record_id' => $this->record->id,
            'user_id' => auth()->id(),
            'action' => 'approved',
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
                $this->notifyStageUsers($nextStage, "A record in {$this->module->name} has advanced and requires your approval.");
                $this->status = $nextStage->default_status ?? 'Under Review';
                $this->approvalComment = '';
                session()->flash('message', 'Approved. Record advanced to next stage.');
                return;
            }
        }

        $this->record->update(['status' => 'Completed', 'current_stage_id' => null]);
        $this->status = 'Completed';
        $this->approvalComment = '';

        // Notify submitter
        $submitter = User::find($this->record->created_by);
        $submitter?->notify(new DynamicNotification("Your record in {$this->module->name} has been completed.", $this->record->id, $this->moduleSlug));

        session()->flash('message', 'Record approved successfully.');
    }

    public function forwardToBranch($index)
    {
        $this->authorizeApprovalAction();

        // Persist any in-progress edits before advancing
        if ($this->record && $this->data) {
            $this->record->update(['data' => $this->data, 'updated_by' => auth()->id()]);
        }

        $currentStage = $this->record->currentStage;
        $branches = $currentStage?->branches_json ?? [];
        $branch = $branches[$index] ?? null;

        if (!$branch || empty($branch['stage_id'])) {
            session()->flash('error', 'Invalid branch.');
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
            $this->notifyStageUsers($targetStage, "A record in {$this->module->name} has been forwarded ({$label}) and requires your action.");
        }

        $this->status = $targetStage?->default_status ?? 'Under Review';
        $this->approvalComment = '';
        session()->flash('message', "Record forwarded: {$label}.");
    }

    public function returnForRevision()
    {
        $this->authorizeApprovalAction();
        $this->validate(['approvalComment' => 'required|string|max:2000'], [], ['approvalComment' => 'revision notes']);

        RecordApproval::create([
            'record_id' => $this->record->id,
            'stage_id' => $this->record->current_stage_id,
            'user_id' => auth()->id(),
            'action' => 'returned',
            'comment' => $this->approvalComment,
        ]);

        RecordHistory::create([
            'record_id' => $this->record->id,
            'user_id' => auth()->id(),
            'action' => 'returned',
            'changes_json' => ['comment' => $this->approvalComment],
        ]);

        $this->record->update(['status' => 'Returned', 'current_stage_id' => null]);
        $this->status = 'Returned';
        $this->approvalComment = '';

        $submitter = User::find($this->record->created_by);
        $submitter?->notify(new DynamicNotification("Your record in {$this->module->name} has been returned for revision.", $this->record->id, $this->moduleSlug));

        session()->flash('message', 'Record returned for revision.');
    }

    // ─── Comments ──────────────────────────────────────────────────────────────

    public function markReviewDone(string $fieldSlug): void
    {
        if (! $this->record) return;

        if (! $this->canReview()) abort(403);

        // Upsert the review record for this user
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

        // Count all unique non-super-admin users with review-{slug} permission
        $permission = \Spatie\Permission\Models\Permission::where('name', "review-{$this->moduleSlug}")
            ->where('guard_name', 'web')->first();

        $reviewerCount = 0;
        if ($permission) {
            $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super admin')->first();
            $reviewerCount = \DB::table('model_has_roles')
                ->join('role_has_permissions', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
                ->where('role_has_permissions.permission_id', $permission->id)
                ->where('model_has_roles.model_type', User::class)
                ->when($superAdminRole, fn($q) => $q->where('model_has_roles.role_id', '!=', $superAdminRole->id))
                ->distinct()
                ->count('model_has_roles.model_id');
        }

        $doneCount = \DB::table('text_editor_reviews')
            ->where('record_id', $this->record->id)
            ->where('field_slug', $fieldSlug)
            ->count();

        if ($reviewerCount > 0 && $doneCount >= $reviewerCount) {
            // All reviewers done — auto-advance to next stage
            $this->approve();
            return;
        }

        $this->dispatch('review-marked-done', fieldSlug: $fieldSlug);
        $this->dispatch('notify', type: 'success', message: 'Review marked as done.');
    }

    public function addComment()
    {
        $this->validate(['newComment' => 'required|string|max:2000']);

        RecordComment::create([
            'record_id' => $this->recordId,
            'user_id' => auth()->id(),
            'body' => $this->newComment,
        ]);

        $this->newComment = '';
    }

    public function deleteComment($commentId)
    {
        if (!auth()->user()->can('delete-comments')) abort(403);
        RecordComment::where('id', $commentId)->where('record_id', $this->recordId)->delete();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function canEditRecord(): bool
    {
        $user = auth()->user();
        if ($user->hasRole('super admin')) return true;
        if ($user->can("edit-{$this->moduleSlug}")) return true;
        return false;
    }

    private function authorizeApprovalAction(): void
    {
        if (!$this->record) abort(403);
        if (auth()->user()->hasRole('super admin')) return;

        $stage = $this->record->currentStage;

        if ($stage && $stage->approver_role_id) {
            $role = Role::find($stage->approver_role_id);
            if ($role && auth()->user()->hasRole($role->name)) return;
        }

        if (auth()->user()->can("approve-{$this->moduleSlug}")) return;

        abort(403);
    }

    private function notifyStageUsers(WorkflowStage $stage, string $message): void
    {
        $configured = $stage->notify_on_enter_json ?? [];

        if (empty($configured)) {
            // Legacy fallback: DB-notify approver role users only
            if (!$stage->approver_role_id) return;
            $role = Role::find($stage->approver_role_id);
            if (!$role) return;
            foreach (User::role($role->name)->get() as $approver) {
                $approver->notify(new DynamicNotification($message, $this->record->id, $this->moduleSlug));
            }
            return;
        }

        $recordUrl = $this->record->id && $this->moduleSlug
            ? url("/app/{$this->moduleSlug}/{$this->record->id}")
            : null;

        foreach ($configured as $recipient) {
            $type  = $recipient['type'] ?? '';
            $value = $recipient['value'] ?? '';

            try {
                if ($type === 'submitter') {
                    $this->sendStageNotification(User::find($this->record->created_by), $message);
                } elseif ($type === 'role' && $value) {
                    foreach (User::role($value)->get() as $user) {
                        $this->sendStageNotification($user, $message);
                    }
                } elseif ($type === 'specific_user' && $value) {
                    $this->sendStageNotification(User::find($value), $message);
                } elseif ($type === 'specific_email' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($value)->send(new StageNotificationMail($message, 'PRMS Notification', $recordUrl));
                }
            } catch (\Throwable $e) {
                Log::warning('[notifyStageUsers] Failed type=' . $type . ': ' . $e->getMessage());
            }
        }
    }

    private function sendStageNotification(?User $user, string $message): void
    {
        if (!$user) return;
        $user->notify(new DynamicNotification($message, $this->record->id, $this->moduleSlug, null, true));
    }

    private function canReview(): bool
    {
        $user = auth()->user();
        if ($user->hasRole('super admin')) return false;
        return $user->can("review-{$this->moduleSlug}");
    }

    private function canAct(): bool
    {
        if (!$this->record || !$this->record->current_stage_id) return false;
        if (auth()->user()->hasRole('super admin')) return true;

        $stage = $this->record->currentStage;

        if ($stage && $stage->approver_role_id) {
            $role = Role::find($stage->approver_role_id);
            if ($role && auth()->user()->hasRole($role->name)) return true;
        }

        return auth()->user()->can("approve-{$this->moduleSlug}");
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $comments = $this->recordId
            ? RecordComment::where('record_id', $this->recordId)->with('user')->oldest()->get()
            : collect();

        $histories = $this->recordId
            ? RecordHistory::where('record_id', $this->recordId)->with('user')->latest()->get()
            : collect();

        $approvals = $this->recordId
            ? RecordApproval::where('record_id', $this->recordId)->with(['user', 'stage'])->latest()->get()
            : collect();

        $canDeleteComments = auth()->user()->can('delete-comments');

        $hasStages = $this->module->workflowStages()->exists();
        $canAct    = $this->canAct();
        $canReview = $this->canReview();
        // Gate::before gives super admin can("create-{slug}") automatically
        $canSubmit = $hasStages
            && $this->module->has_submit_button
            && (!$this->recordId || in_array($this->record->status ?? '', ['Draft', 'Returned']))
            && auth()->user()->can("create-{$this->moduleSlug}");
        $currentStage = $this->record?->currentStage;

        $reviewedFields = $this->recordId
            ? \DB::table('text_editor_reviews')
                ->where('record_id', $this->recordId)
                ->where('user_id', auth()->id())
                ->pluck('field_slug')->all()
            : [];

        $reviewersByField = $this->recordId
            ? \DB::table('text_editor_reviews')
                ->join('users', 'text_editor_reviews.user_id', '=', 'users.id')
                ->where('text_editor_reviews.record_id', $this->recordId)
                ->orderBy('text_editor_reviews.reviewed_at')
                ->select('text_editor_reviews.field_slug', 'users.name')
                ->get()
                ->groupBy('field_slug')
            : collect();

        // Re-mint editor tokens if lost after Livewire re-hydration (protected property not in snapshot)
        if (empty($this->editorTokens)) {
            $tokenPrefix = 'editor-' . ($this->recordId ?? 'new') . '-';
            foreach ($this->module->fields as $field) {
                if ($field->type === 'text_editor') {
                    $this->editorTokens[$field->slug] = auth()->user()
                        ->createToken($tokenPrefix . $field->slug, ['editor:read', 'editor:write'], now()->addHours(8))
                        ->plainTextToken;
                }
            }
        }
        $editorTokens = $this->editorTokens;

        return view('livewire.builder.dynamic-record-form',
            compact('comments', 'canDeleteComments', 'histories', 'approvals', 'hasStages', 'canAct', 'canReview', 'canSubmit', 'currentStage', 'reviewedFields', 'reviewersByField', 'editorTokens'));
    }
}
