<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class WorkflowStage extends Model
{
    protected $fillable = [
        'module_id', 'name', 'order', 'approver_role_id', 'reviewer_role_id',
        'requires_all_approvers', 'is_final_approval', 'has_return_button', 'allow_edit', 'default_status', 'stage_type',
        'auto_advance_days',
        'branch_ad_referendum_stage_id', 'branch_trc_stage_id',
        'branches_json', 'stage_fields_json', 'notify_on_enter_json', 'date_reminders_json',
    ];

    protected $casts = [
        'requires_all_approvers' => 'boolean',
        'is_final_approval' => 'boolean',
        'has_return_button' => 'boolean',
        'allow_edit' => 'boolean',
        'auto_advance_days' => 'integer',
        'branches_json'        => 'array',
        'stage_fields_json'    => 'array',
        'notify_on_enter_json' => 'array',
        'date_reminders_json'  => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function approverRole()
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function reviewerRole()
    {
        return $this->belongsTo(Role::class, 'reviewer_role_id');
    }

    public function approvals()
    {
        return $this->hasMany(RecordApproval::class, 'stage_id');
    }

    public function branchAdReferendumStage()
    {
        return $this->belongsTo(WorkflowStage::class, 'branch_ad_referendum_stage_id');
    }

    public function branchTrcStage()
    {
        return $this->belongsTo(WorkflowStage::class, 'branch_trc_stage_id');
    }

    public function hasBranches(): bool
    {
        return !empty($this->branches_json)
            || $this->branch_ad_referendum_stage_id !== null
            || $this->branch_trc_stage_id !== null;
    }
}
