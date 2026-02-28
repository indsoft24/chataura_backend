<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletPackage extends Model
{
    protected $fillable = [
        'coin_amount',
        'price_in_inr',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'coin_amount' => 'integer',
            'price_in_inr' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
