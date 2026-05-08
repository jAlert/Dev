<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginSlide extends Model
{
    protected $fillable = ['title', 'subtitle', 'image_path', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
