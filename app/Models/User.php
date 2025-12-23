<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'bio',
        'avatar_url',
        'account_private',
        'language',
        'notification_settings',
        'account_type',
        'badge_status',
        'badge_application_reason',
        'badge_is_culinary_creator',
        'badge_applied_at',
        'badge_rejection_reason',
        'show_badge',
        'website',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'followers_count',
        'following_count',
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
            'account_private' => 'boolean',
            'notification_settings' => 'array',
            'badge_is_culinary_creator' => 'boolean',
            'show_badge' => 'boolean',
            'badge_applied_at' => 'datetime',
        ];
    }

    /**
     * User's videos
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /**
     * User's likes
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /**
     * User's comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * User's bookmarks
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * User's notifications
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * User's device tokens for push notifications
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Users that this user is following
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    /**
     * Users that are following this user
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Check if this user is following another user
     */
    public function isFollowing(int $userId): bool
    {
        return $this->following()->where('following_id', $userId)->exists();
    }

    /**
     * Check if this user is followed by another user
     */
    public function isFollowedBy(int $userId): bool
    {
        return $this->followers()->where('follower_id', $userId)->exists();
    }

    /**
     * Get the followers count attribute
     */
    public function getFollowersCountAttribute(): int
    {
        return $this->followers()->count();
    }

    /**
     * Get the following count attribute
     */
    public function getFollowingCountAttribute(): int
    {
        return $this->following()->count();
    }

    /**
     * Get avatar URL attribute - fix localhost URLs for mobile access
     */
    public function getAvatarUrlAttribute($value): ?string
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
     * Users that this user has blocked
     */
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'blocked_users', 'blocker_id', 'blocked_id')
            ->withTimestamps();
    }

    /**
     * Users that have blocked this user
     */
    public function blockedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'blocked_users', 'blocked_id', 'blocker_id')
            ->withTimestamps();
    }

    /**
     * Check if this user has blocked another user
     */
    public function hasBlocked(int $userId): bool
    {
        return $this->blockedUsers()->where('blocked_id', $userId)->exists();
    }

    /**
     * Check if this user is blocked by another user
     */
    public function isBlockedBy(int $userId): bool
    {
        return $this->blockedBy()->where('blocker_id', $userId)->exists();
    }
}
