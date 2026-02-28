<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralHistory extends Model
{
    protected $table = 'referral_history';

    protected $fillable = [
        'referrer_id',
        'referee_id',
        'referrer_amount',
        'referee_amount',
    ];

    protected function casts(): array
    {
        return [
            'referrer_amount' => 'integer',
            'referee_amount' => 'integer',
        ];
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee()
    {
        return $this->belongsTo(User::class, 'referee_id');
    }
}
