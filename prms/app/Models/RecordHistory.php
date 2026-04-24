<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordHistory extends Model
{
    protected $fillable = ['record_id', 'user_id', 'action', 'changes_json'];

    protected $casts = [
        'changes_json' => 'array',
    ];

    public function record()
    {
        return $this->belongsTo(Record::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
