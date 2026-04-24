<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    protected $fillable = ['module_id', 'name', 'trigger', 'conditions_json'];
    
    protected $casts = [
        'conditions_json' => 'array',
    ];

    public function actions()
    {
        return $this->hasMany(WorkflowAction::class);
    }
}
