<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['webhook_id', 'event', 'payload', 'response_code', 'response_body', 'success'];

    protected $casts = [
        'payload'    => 'array',
        'success'    => 'boolean',
        'created_at' => 'datetime',
    ];

    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }
}
