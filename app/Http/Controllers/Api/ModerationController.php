<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ModerationController extends Controller
{
    /**
     * Get all pending videos for moderation
     * Only accessible by admin users
     */
    public function getPendingVideos(Request $request)
    {
        // Check if user is admin
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $videos = Video::where('status', 'pending')
            ->with('user:id,name,username,avatar_url')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'videos' => $videos->items(),
            'current_page' => $videos->currentPage(),
            'last_page' => $videos->lastPage(),
            'total' => $videos->total(),
        ]);
    }

    /**
     * Approve a video
     * Only accessible by admin users
     */
    public function approveVideo(Request $request, $videoId)
    {
        // Check if user is admin
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $video = Video::find($videoId);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found'
            ], 404);
        }

        $video->update([
            'status' => 'approved',
            'moderated_by' => $user->id,
            'moderated_at' => now(),
        ]);

        // Clear video feed cache when video is approved
        $this->clearVideoFeedCache();

        return response()->json([
            'message' => 'Video approved successfully',
            'video' => $video
        ]);
    }

    /**
     * Reject a video
     * Only accessible by admin users
     */
    public function rejectVideo(Request $request, $videoId)
    {
        // Check if user is admin
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        $video = Video::find($videoId);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found'
            ], 404);
        }

        $video->update([
            'status' => 'rejected',
            'moderated_by' => $user->id,
            'moderated_at' => now(),
            'rejection_reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Video rejected successfully',
            'video' => $video
        ]);
    }

    /**
     * Get moderation statistics
     * Only accessible by admin users
     */
    public function getModerationStats(Request $request)
    {
        // Check if user is admin
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $stats = [
            'pending' => Video::where('status', 'pending')->count(),
            'approved' => Video::where('status', 'approved')->count(),
            'rejected' => Video::where('status', 'rejected')->count(),
            'total' => Video::count(),
        ];

        return response()->json($stats);
    }

    /**
     * Clear video feed cache
     */
    private function clearVideoFeedCache()
    {
        // Clear all video feed cache pages (assuming max 100 pages)
        for ($page = 1; $page <= 100; $page++) {
            Cache::forget("video_feed_page_{$page}");
        }
    }
}
