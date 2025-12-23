<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HighlightItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'highlight_id',
        'story_id',
        'order',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    /**
     * Relationship: Item belongs to a highlight
     */
    public function highlight(): BelongsTo
    {
        return $this->belongsTo(Highlight::class, 'highlight_id');
    }

    /**
     * Relationship: Item belongs to a story
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
