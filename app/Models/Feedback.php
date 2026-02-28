<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = ['user_id', 'message', 'screenshot_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
