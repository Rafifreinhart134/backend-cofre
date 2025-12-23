<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Highlight extends Model
{
    protected $table = 'story_highlights';

    protected $fillable = [
        'user_id',
        'title',
        'cover_image_url',
        'order',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Highlight belongs to a user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Highlight has many items
     */
    public function items(): HasMany
    {
        return $this->hasMany(HighlightItem::class, 'highlight_id');
    }

    /**
     * Relationship: Highlight has many stories through items
     */
    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'highlight_items', 'highlight_id', 'story_id')
            ->withPivot('order', 'added_at')
            ->orderByPivot('order', 'asc');
    }

    /**
     * Get the count of stories in this highlight
     */
    public function getStoriesCountAttribute(): int
    {
        return $this->items()->count();
    }
}
