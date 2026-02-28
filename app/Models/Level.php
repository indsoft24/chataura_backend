<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    public $incrementing = false;

    protected $keyType = 'integer';

    protected $fillable = [
        'id',
        'min_xp',
        'max_xp',
        'animation_key',
    ];

    protected function casts(): array
    {
        return [
            'min_xp' => 'integer',
            'max_xp' => 'integer',
        ];
    }

    /**
     * Whether the given XP value falls within this level's range.
     */
    public function containsXp(int $xp): bool
    {
        return $xp >= $this->min_xp && $xp <= $this->max_xp;
    }
}
