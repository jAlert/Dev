<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'source_module_id', 'default_status', 'my_records_only', 'sort_order', 'has_submit_button', 'has_return_button', 'has_draft_button'];

    protected static function booted(): void
    {
        static::creating(function ($module) {
            $module->sort_order = static::max('sort_order') + 1;
        });
    }

    public function fields()
    {
        return $this->hasMany(ModuleField::class);
    }

    public function records()
    {
        return $this->hasMany(Record::class);
    }

    public function workflowStages()
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('order');
    }

    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }
}
