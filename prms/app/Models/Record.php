<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Record extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Record $record) {
            if (empty($record->data)) return;

            $attachmentSlugs = $record->module?->fields()
                ->where('type', 'attachment')
                ->pluck('slug')
                ->toArray() ?? [];

            foreach ($attachmentSlugs as $slug) {
                $val = $record->data[$slug] ?? null;
                if (empty($val)) continue;

                if (is_string($val)) {
                    Storage::disk('public')->delete($val);
                } elseif (is_array($val)) {
                    foreach ($val as $version) {
                        if (!empty($version['path'])) {
                            Storage::disk('public')->delete($version['path']);
                        }
                    }
                }
            }
        });
    }

    protected $fillable = ['module_id', 'data', 'status', 'current_stage_id', 'stage_entered_at', 'assigned_to', 'created_by', 'updated_by'];

    protected $casts = [
        'data' => 'array',
        'stage_entered_at' => 'datetime',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function comments()
    {
        return $this->hasMany(RecordComment::class);
    }

    public function currentStage()
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approvals()
    {
        return $this->hasMany(RecordApproval::class);
    }

    public function histories()
    {
        return $this->hasMany(RecordHistory::class);
    }
}
