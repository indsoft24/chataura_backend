<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const ROLE_USER = 'user';
    public const ROLE_SELLER = 'seller';
    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'role',
        'name',
        'phone',
        'email',
        'display_name',
        'avatar_url',
        'level',
        'exp',
        'xp',
        'selected_frame_id',
        'coin_balance',
        'wallet_balance',
        'total_earned_coins',
        'referral_balance',
        'language',
        'country',
        'bio',
        'gender',
        'dob',
        'gems',
        'private_account',
        'show_online_status',
        'message_notifications',
        'room_notifications',
        'gift_notifications',
        'fcm_token',
        'invite_code',
        'invited_by',
        'password',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        'level' => 'integer',
        'exp' => 'integer',
        'xp' => 'integer',
        'coin_balance' => 'integer',
        'wallet_balance' => 'integer',
        'total_earned_coins' => 'integer',
        'referral_balance' => 'integer',
        'gems' => 'integer',
        'private_account' => 'boolean',
        'show_online_status' => 'boolean',
        'message_notifications' => 'boolean',
        'room_notifications' => 'boolean',
        'gift_notifications' => 'boolean',
        'dob' => 'date',
        'last_seen_at' => 'datetime',
    ];
    }

    /**
     * Get the rooms owned by this user.
     */
    public function ownedRooms()
    {
        return $this->hasMany(Room::class, 'owner_id');
    }

    /**
     * Get the room memberships.
     */
    public function roomMemberships()
    {
        return $this->hasMany(RoomMember::class);
    }

    /**
     * Get the seats occupied by this user.
     */
    public function seats()
    {
        return $this->hasMany(Seat::class);
    }

    /**
     * Get transactions where this user is the sender.
     */
    public function sentTransactions()
    {
        return $this->hasMany(Transaction::class, 'sender_id');
    }

    /**
     * Get transactions where this user is the receiver.
     */
    public function receivedTransactions()
    {
        return $this->hasMany(Transaction::class, 'receiver_id');
    }

    /**
     * Get the user who invited this user.
     */
    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get users invited by this user.
     */
    public function invitedUsers()
    {
        return $this->hasMany(User::class, 'invited_by');
    }

    /**
     * Get refresh tokens for this user.
     */
    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function friendships()
    {
        return $this->hasMany(Friendship::class);
    }

    public function friends()
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
            ->withPivot('status')
            ->wherePivot('status', Friendship::STATUS_ACCEPTED)
            ->withTimestamps();
    }

    public function followers()
    {
        return $this->hasMany(UserFollower::class, 'following_id');
    }

    public function following()
    {
        return $this->hasMany(UserFollower::class, 'follower_id');
    }

    public function blockedUsers()
    {
        return $this->hasMany(BlockedUser::class, 'blocker_id');
    }

    public function blockedBy()
    {
        return $this->hasMany(BlockedUser::class, 'blocked_id');
    }

    public function isSeller(): bool
    {
        return $this->role === self::ROLE_SELLER;
    }

    public function selectedFrame()
    {
        return $this->belongsTo(Frame::class, 'selected_frame_id');
    }

    public function unlockedFrames()
    {
        return $this->belongsToMany(Frame::class, 'user_unlocked_frames')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function conversationParticipants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * User is considered online if they had activity in the last 5 minutes.
     */
    public function isOnline(int $withinMinutes = 5): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }
        return $this->last_seen_at->gte(now()->subMinutes($withinMinutes));
    }

    /**
     * Return is_online and last_seen_at for API, respecting show_online_status.
     * If target has show_online_status = false and viewer is not the target, mask to false / null.
     */
    public static function getOnlineStatusForViewer(self $target, int $viewerUserId): array
    {
        if ((int) $target->id === $viewerUserId) {
            return [
                'is_online' => $target->isOnline(),
                'last_seen_at' => $target->last_seen_at?->toIso8601String(),
            ];
        }
        if ($target->show_online_status === false) {
            return ['is_online' => false, 'last_seen_at' => null];
        }
        return [
            'is_online' => $target->isOnline(),
            'last_seen_at' => $target->last_seen_at?->toIso8601String(),
        ];
    }
}
