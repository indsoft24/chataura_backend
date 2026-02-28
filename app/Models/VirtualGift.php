<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualGift extends Model
{
    protected $fillable = [
        'name',
        'image_url',
        'animation_url',
        'coin_cost',
        'is_active',
        'rarity',
    ];

    protected function casts(): array
    {
        return [
            'coin_cost' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
