<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $table = 'admin_settings';

    protected $fillable = [
        'gift_commission_percent',
        'audio_call_price_per_min',
        'audio_call_commission_percent',
        'video_call_price_per_min',
        'video_call_commission_percent',
        'referral_reward_referrer',
        'referral_reward_referee',
        'referral_coin_conversion_rate',
    ];

    protected function casts(): array
    {
        return [
            'gift_commission_percent' => 'integer',
            'audio_call_price_per_min' => 'integer',
            'audio_call_commission_percent' => 'integer',
            'video_call_price_per_min' => 'integer',
            'video_call_commission_percent' => 'integer',
            'referral_reward_referrer' => 'integer',
            'referral_reward_referee' => 'integer',
            'referral_coin_conversion_rate' => 'integer',
        ];
    }

    /**
     * Get the single row of admin settings (singleton).
     */
    public static function get(): self
    {
        $row = self::first();
        if (!$row) {
            $row = self::create([
                'gift_commission_percent' => 20,
                'audio_call_price_per_min' => 10,
                'audio_call_commission_percent' => 30,
                'video_call_price_per_min' => 20,
                'video_call_commission_percent' => 30,
                'referral_reward_referrer' => 100,
                'referral_reward_referee' => 50,
                'referral_coin_conversion_rate' => 1,
            ]);
        }
        return $row;
    }
}
