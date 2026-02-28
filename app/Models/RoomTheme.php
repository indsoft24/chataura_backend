<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomTheme extends Model
{
    protected $fillable = [
        'name',
        'type',
        'media_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public const TYPE_STATIC_IMAGE = 'static_image';
    public const TYPE_LOTTIE_ANIMATION = 'lottie_animation';
}
