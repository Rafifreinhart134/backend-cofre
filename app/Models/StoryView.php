<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'story_id',
        'viewer_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    /**
     * Relationship: View belongs to a story
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Relationship: View belongs to a viewer (user)
     */
    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    /**
     * Boot method to set viewed_at automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($view) {
            if (!$view->viewed_at) {
                $view->viewed_at = now();
            }
        });
    }
}
