<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowStageTemplate extends Model
{
    protected $fillable = ['name', 'stages_json'];
    protected $casts    = ['stages_json' => 'array'];
}
