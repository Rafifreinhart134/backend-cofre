<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CommentController extends Controller
{
    /**
     * Get comments for a video
     */
    public function index($videoId)
    {
        $video = Video::findOrFail($videoId);
        $comments = $video->comments()
            ->with('user:id,name')
            ->latest()
            ->paginate(20);

        return response()->json($comments);
    }

    /**
     * Store a new comment
     */
    public function store(Request $request, $videoId)
    {
        $request->validate([
            'content' => [
                'required',
                'string',
                'min:1',
                'max:500',
                // Reject HTML/script tags for XSS protection
                function ($attribute, $value, $fail) {
                    if (preg_match('/<[^>]*>/', $value)) {
                        $fail('Comments cannot contain HTML tags.');
                    }
                    // Reject javascript: protocol and event handlers
                    if (preg_match('/javascript:|on\w+\s*=/i', $value)) {
                        $fail('Comments contain disallowed content.');
                    }
                },
            ],
        ]);

        $video = Video::findOrFail($videoId);
        $user = $request->user();

        // Sanitize content: strip tags and trim
        $sanitizedContent = strip_tags(trim($request->content));

        $comment = Comment::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'content' => $sanitizedContent,
        ]);

        $comment->load('user:id,name');

        // Create notification (only if not own video)
        if ($video->user_id !== $user->id) {
            \App\Models\Notification::create([
                'user_id' => $video->user_id,
                'from_user_id' => $user->id,
                'type' => 'comment',
                'message' => 'mengomentari video Anda',
                'video_id' => $video->id,
                'comment_id' => $comment->id,
            ]);
        }

        // Clear video feed cache since comment count affects recommendation score
        $this->clearVideoFeedCache();

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment,
        ], 201);
    }

    /**
     * Delete a comment
     */
    public function destroy(Request $request, $commentId)
    {
        $comment = Comment::findOrFail($commentId);
        $user = $request->user();

        // Only comment owner can delete
        if ($comment->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to delete this comment',
            ], 403);
        }

        $comment->delete();

        // Clear video feed cache since comment count affects recommendation score
        $this->clearVideoFeedCache();

        return response()->json([
            'message' => 'Comment deleted successfully',
        ]);
    }

    /**
     * Clear video feed cache for all pages
     */
    private function clearVideoFeedCache(): void
    {
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("video_feed_page_{$page}");
        }
    }
}
