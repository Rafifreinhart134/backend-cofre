<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LikeController extends Controller
{
    /**
     * Toggle like on a video
     */
    public function toggle(Request $request, $videoId)
    {
        $request->validate([
            // videoId dari URL parameter
        ]);

        $user = $request->user();
        $video = Video::findOrFail($videoId);

        // Check if already liked
        $existingLike = Like::where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->first();

        if ($existingLike) {
            // Unlike
            $existingLike->delete();

            // Delete the like notification
            \App\Models\Notification::where('user_id', $video->user_id)
                ->where('from_user_id', $user->id)
                ->where('type', 'like')
                ->where('video_id', $video->id)
                ->delete();

            // Clear video feed cache since like count affects recommendation score
            $this->clearVideoFeedCache();

            return response()->json([
                'message' => 'Video unliked',
                'liked' => false,
                'likes_count' => $video->likes()->count(),
            ]);
        } else {
            // Like
            Like::create([
                'user_id' => $user->id,
                'video_id' => $video->id,
            ]);

            // Create notification (only if not own video)
            if ($video->user_id !== $user->id) {
                \App\Models\Notification::create([
                    'user_id' => $video->user_id,
                    'from_user_id' => $user->id,
                    'type' => 'like',
                    'message' => 'menyukai video Anda',
                    'video_id' => $video->id,
                ]);
            }

            // Clear video feed cache since like count affects recommendation score
            $this->clearVideoFeedCache();

            return response()->json([
                'message' => 'Video liked',
                'liked' => true,
                'likes_count' => $video->likes()->count(),
            ]);
        }
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
