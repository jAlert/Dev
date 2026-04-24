<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowAction extends Model
{
    protected $fillable = ['workflow_id', 'type', 'config_json'];
    
    protected $casts = [
        'config_json' => 'array',
    ];
}
