<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'user_id',
        'video_id',
        'story_id',
        'reportable_type',
        'reason',
        'details',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function story()
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Get the reportable item (video or story)
     */
    public function getReportableAttribute()
    {
        if ($this->reportable_type === 'story' && $this->story_id) {
            return $this->story;
        }
        return $this->video;
    }
}
