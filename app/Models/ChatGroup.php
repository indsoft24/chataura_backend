<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatGroup extends Model
{
    protected $table = 'chat_groups';

    protected $fillable = [
        'name', 'image_url', 'type', 'is_private', 'allow_visitors', 'auto_approve',
        'created_by', 'conversation_id',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'allow_visitors' => 'boolean',
            'auto_approve' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'chat_group_id');
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members', 'chat_group_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }
}
