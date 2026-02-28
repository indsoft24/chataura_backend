<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinTransaction extends Model
{
    public const TYPE_GIFT = 'GIFT';
    public const TYPE_AUDIO_CALL = 'AUDIO_CALL';
    public const TYPE_VIDEO_CALL = 'VIDEO_CALL';
    public const TYPE_CALL_COMMISSION = 'CALL_COMMISSION'; // Platform share from call billing
    public const TYPE_SELLER_TRANSFER = 'SELLER_TRANSFER'; // Seller/admin transfer to user
    public const TYPE_WITHDRAWAL = 'WITHDRAWAL'; // Gems withdrawal request (deduction from user)

    public $timestamps = false;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'transaction_type',
        'reference_id',
        'gross_coins_deducted',
        'admin_commission_coins',
        'net_coins_received',
    ];

    protected function casts(): array
    {
        return [
            'reference_id' => 'integer',
            'gross_coins_deducted' => 'integer',
            'admin_commission_coins' => 'integer',
            'net_coins_received' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
