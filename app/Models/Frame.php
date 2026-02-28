<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Frame extends Model
{
    protected $fillable = [
        'level_required',
        'name',
        'animation_key',
        'animation_json',
        'is_premium',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level_required' => 'integer',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'animation_json' => 'array',
        ];
    }

    /**
     * Scope to only active frames.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Users who have unlocked this frame.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_unlocked_frames')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
