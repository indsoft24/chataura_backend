<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaPost extends Model
{
    use HasFactory;

    public const TYPE_REEL = 'reel';
    public const TYPE_POST = 'post';

    public const MEDIA_TYPE_VIDEO = 'video';
    public const MEDIA_TYPE_IMAGE = 'image';

    /**
     * Fields selected for feed queries (performance).
     */
    public const FEED_SELECT = [
        'id',
        'user_id',
        'type',
        'media_type',
        'file_url',
        'thumbnail_url',
        'music_url',
        'effect_name',
        'duration',
        'aspect_ratio',
        'is_camera_recorded',
        'caption',
        'likes',
        'comments',
        'shares',
        'created_at',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'media_type',
        'file_url',
        'thumbnail_url',
        'music_url',
        'effect_name',
        'duration',
        'aspect_ratio',
        'is_camera_recorded',
        'caption',
        'likes',
        'comments',
        'shares',
    ];

    protected function casts(): array
    {
        return [
            'likes' => 'integer',
            'comments' => 'integer',
            'shares' => 'integer',
            'duration' => 'integer',
            'is_camera_recorded' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class, 'media_post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class, 'media_post_id');
    }

    public function saves(): HasMany
    {
        return $this->hasMany(PostSave::class, 'media_post_id');
    }

    public function scopeReels(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_REEL);
    }

    public function scopePosts(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_POST);
    }

    public function scopeTrending(Builder $query): Builder
    {
        return $query->orderByRaw('(likes + comments) DESC');
    }

    /**
     * Transform to feed response format (CDN URLs already stored).
     */
    public function toFeedItem(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'media_type' => $this->media_type,
            'file_url' => $this->file_url,
            'thumbnail_url' => $this->thumbnail_url,
            'caption' => $this->caption,
            'music_url' => $this->music_url,
            'effect_name' => $this->effect_name,
            'duration' => $this->duration,
            'aspect_ratio' => $this->aspect_ratio,
            'is_camera_recorded' => (bool) $this->is_camera_recorded,
            'likes' => (int) $this->likes,
            'comments' => (int) $this->comments,
            'shares' => (int) ($this->shares ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
