<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'coin_price',
        'image_url',
        'animation_url',
        'animation_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'coin_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get transactions for this gift type.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}

