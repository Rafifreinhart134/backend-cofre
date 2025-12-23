<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        's3_url',
        'thumbnail_url',
        'menu_data',
    ];

    protected $casts = [
        'menu_data' => 'array',
    ];

    protected $appends = [
        'likes_count',
        'comments_count',
        'views_count',
    ];

    /**
     * Relasi ke User (Creator)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Likes
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /**
     * Relasi ke Comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Relasi ke Views
     */
    public function views(): HasMany
    {
        return $this->hasMany(View::class);
    }

    /**
     * Relasi ke Bookmarks
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Relasi ke Video Tags
     */
    public function tags(): HasMany
    {
        return $this->hasMany(VideoTag::class);
    }

    /**
     * Get approved tagged users
     */
    public function taggedUsers()
    {
        return $this->belongsToMany(User::class, 'video_tags', 'video_id', 'tagged_user_id')
            ->wherePivot('status', 'approved')
            ->withTimestamps();
    }

    /**
     * Get likes count attribute
     */
    public function getLikesCountAttribute(): int
    {
        return $this->likes()->count();
    }

    /**
     * Get comments count attribute
     */
    public function getCommentsCountAttribute(): int
    {
        return $this->comments()->count();
    }

    /**
     * Get views count attribute
     */
    public function getViewsCountAttribute(): int
    {
        return $this->views()->count();
    }

    /**
     * Check if user liked this video
     */
    public function isLikedBy(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }
        return $this->likes()->where('user_id', $userId)->exists();
    }

    /**
     * Check if user bookmarked this video
     */
    public function isBookmarkedBy(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }
        return $this->bookmarks()->where('user_id', $userId)->exists();
    }

    /**
     * Get s3 URL attribute - fix localhost URLs for mobile access
     */
    public function getS3UrlAttribute($value): ?string
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
}
