<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'tagged_user_id',
        'tagged_by_user_id',
        'status',
        'tag_type',
        'comment_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the video that owns the tag
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Get the user who was tagged
     */
    public function taggedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tagged_user_id');
    }

    /**
     * Get the user who created the tag
     */
    public function taggedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tagged_by_user_id');
    }

    /**
     * Get the comment (if tag is in comment)
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * Approve the tag
     */
    public function approve()
    {
        $this->update(['status' => 'approved']);

        // Create notification for tag approval
        Notification::create([
            'user_id' => $this->tagged_by_user_id,
            'from_user_id' => $this->tagged_user_id,
            'type' => 'tag_approved',
            'message' => 'menerima tag Anda',
            'video_id' => $this->video_id,
        ]);
    }

    /**
     * Reject the tag
     */
    public function reject()
    {
        $this->update(['status' => 'rejected']);
    }

    /**
     * Scope: Get pending tags
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get approved tags
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
