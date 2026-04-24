<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = ['name', 'module_id', 'url', 'events', 'secret', 'is_active'];

    protected $casts = [
        'events'    => 'array',
        'is_active' => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function logs()
    {
        return $this->hasMany(WebhookLog::class);
    }
}
