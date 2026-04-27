<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleField extends Model
{
    protected $fillable = ['module_id', 'name', 'slug', 'type', 'is_required', 'options_json', 'description', 'sort_order', 'show_in_index', 'col_span', 'versioning', 'visibility_conditions'];

    protected $casts = [
        'is_required'            => 'boolean',
        'show_in_index'          => 'boolean',
        'col_span'               => 'integer',
        'versioning'             => 'boolean',
        'options_json'           => 'array',
        'visibility_conditions'  => 'array',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
