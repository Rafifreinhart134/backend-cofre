<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Story extends Model
{
    protected $fillable = [
        'user_id',
        'media_url',
        'thumbnail_url',
        'media_type',
        'duration',
        'caption',
        'stickers',
        'text_elements',
        'filter',
        'view_count',
        'is_archived',
        'allow_resharing',
        'expires_at',
    ];

    protected $casts = [
        'stickers' => 'array',
        'text_elements' => 'array',
        'is_archived' => 'boolean',
        'allow_resharing' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['is_expired'];

    /**
     * Relationship: Story belongs to a user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Story has many views
     */
    public function views(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    /**
     * Check if story is expired (older than 24 hours)
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get media URL attribute - fix localhost URLs for mobile access
     */
    public function getMediaUrlAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        // Always replace localhost/127.0.0.1 with proper IP for mobile access
        if (str_contains($value, 'localhost') || str_contains($value, '127.0.0.1')) {
            // Use env variable MOBILE_APP_URL or fallback to local IP
            $mobileUrl = env('MOBILE_APP_URL', 'http://192.168.1.7:8000');
            $pattern = '/https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/';

            $originalValue = $value;
            $value = preg_replace($pattern, $mobileUrl, $value);

            // Debug logging (can be removed in production)
            \Log::info('Story media URL transformed', [
                'original' => $originalValue,
                'transformed' => $value,
                'mobile_url' => $mobileUrl
            ]);
        }

        return $value;
    }

    /**
     * Get thumbnail URL attribute - fix localhost URLs for mobile access
     */
    public function getThumbnailUrlAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        // Always replace localhost/127.0.0.1 with proper IP for mobile access
        if (str_contains($value, 'localhost') || str_contains($value, '127.0.0.1')) {
            // Use env variable MOBILE_APP_URL or fallback to local IP
            $mobileUrl = env('MOBILE_APP_URL', 'http://192.168.1.7:8000');
            $pattern = '/https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/';
            $value = preg_replace($pattern, $mobileUrl, $value);
        }

        return $value;
    }

    /**
     * Check if user has viewed this story
     */
    public function hasViewedBy($userId): bool
    {
        return $this->views()->where('viewer_id', $userId)->exists();
    }

    /**
     * Scope: Get active (non-expired, non-archived) stories
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', Carbon::now())
                    ->where('is_archived', false);
    }

    /**
     * Scope: Get stories from followed users
     */
    public function scopeFromFollowing(Builder $query, $userId): Builder
    {
        return $query->whereIn('user_id', function($subQuery) use ($userId) {
            $subQuery->select('following_id')
                    ->from('follows')
                    ->where('follower_id', $userId);
        });
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Boot method to set expires_at automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($story) {
            if (!$story->expires_at) {
                $story->expires_at = Carbon::now()->addHours(24);
            }
        });
    }
}
