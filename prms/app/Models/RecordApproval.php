<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordApproval extends Model
{
    protected $fillable = ['record_id', 'stage_id', 'user_id', 'action', 'comment'];

    public function record()
    {
        return $this->belongsTo(Record::class);
    }

    public function stage()
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
