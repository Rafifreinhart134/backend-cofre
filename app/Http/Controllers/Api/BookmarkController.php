<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Video;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    /**
     * Get user's bookmarked videos
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $bookmarks = $user->bookmarks()
            ->with(['video' => function ($query) {
                $query->with('user:id,name')
                    ->where('status', 'approved');
            }])
            ->latest()
            ->paginate(20);

        return response()->json($bookmarks);
    }

    /**
     * Toggle bookmark on a video
     */
    public function toggle(Request $request, $videoId)
    {
        $user = $request->user();
        $video = Video::findOrFail($videoId);

        $existingBookmark = Bookmark::where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->first();

        if ($existingBookmark) {
            // Remove bookmark
            $existingBookmark->delete();
            return response()->json([
                'message' => 'Bookmark removed',
                'bookmarked' => false,
            ]);
        } else {
            // Add bookmark
            Bookmark::create([
                'user_id' => $user->id,
                'video_id' => $video->id,
            ]);
            return response()->json([
                'message' => 'Video bookmarked',
                'bookmarked' => true,
            ]);
        }
    }
}
