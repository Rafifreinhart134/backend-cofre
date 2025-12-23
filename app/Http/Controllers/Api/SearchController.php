<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search - returns both users and videos
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $currentUser = $request->user();
        $currentUserId = $currentUser ? $currentUser->id : null;

        // If empty query, return suggested users (most followed)
        if (empty($query)) {
            $suggestedUsers = User::withCount('followers')
                ->where('id', '!=', $currentUserId) // Exclude current user
                ->orderBy('followers_count', 'desc')
                ->limit(10)
                ->get();

            // Get all following status in single query (avoid N+1)
            if ($currentUserId && !$suggestedUsers->isEmpty()) {
                $userIds = $suggestedUsers->pluck('id')->toArray();
                $followingIds = \App\Models\Follow::where('follower_id', $currentUserId)
                    ->whereIn('following_id', $userIds)
                    ->pluck('following_id')
                    ->toArray();

                $suggestedUsers = $suggestedUsers->map(function ($user) use ($followingIds) {
                    $user->is_following = in_array($user->id, $followingIds);
                    return $user;
                });
            } else {
                $suggestedUsers = $suggestedUsers->map(function ($user) {
                    $user->is_following = false;
                    return $user;
                });
            }

            return response()->json([
                'users' => $suggestedUsers,
                'videos' => [],
            ]);
        }

        // Search users (with eager loading to prevent N+1)
        // SECURITY: Don't search by email to prevent email enumeration
        $users = User::withCount('followers')
            ->where('name', 'LIKE', "%{$query}%")
            ->select('id', 'name', 'created_at') // Don't include email
            ->limit(10)
            ->get();

        // Get following status in single query
        if ($currentUserId && !$users->isEmpty()) {
            $userIds = $users->pluck('id')->toArray();
            $followingIds = \App\Models\Follow::where('follower_id', $currentUserId)
                ->whereIn('following_id', $userIds)
                ->pluck('following_id')
                ->toArray();

            $users = $users->map(function ($user) use ($followingIds) {
                $user->is_following = in_array($user->id, $followingIds);

                // Get user's top 3 videos (eager loaded with counts)
                $user->videos = Video::where('user_id', $user->id)
                    ->withCount(['likes', 'comments', 'views'])
                    ->orderBy('created_at', 'desc')
                    ->limit(3)
                    ->get();

                return $user;
            });
        } else {
            $users = $users->map(function ($user) {
                $user->is_following = false;
                $user->videos = [];
                return $user;
            });
        }

        // Search videos by description or menu data
        $videos = Video::with('user:id,name')
            ->withCount(['likes', 'comments', 'views'])
            ->where(function ($q) use ($query) {
                $q->where('description', 'LIKE', "%{$query}%")
                  ->orWhere('menu_data', 'LIKE', "%{$query}%");
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Add is_liked, is_bookmarked flags
        if ($currentUserId && !$videos->isEmpty()) {
            $videoIds = $videos->pluck('id')->toArray();

            $likedVideoIds = \App\Models\Like::where('user_id', $currentUserId)
                ->whereIn('video_id', $videoIds)
                ->pluck('video_id')
                ->toArray();

            $bookmarkedVideoIds = \App\Models\Bookmark::where('user_id', $currentUserId)
                ->whereIn('video_id', $videoIds)
                ->pluck('video_id')
                ->toArray();

            $videos = $videos->map(function ($video) use ($likedVideoIds, $bookmarkedVideoIds) {
                $video->is_liked = in_array($video->id, $likedVideoIds);
                $video->is_bookmarked = in_array($video->id, $bookmarkedVideoIds);
                return $video;
            });
        } else {
            $videos = $videos->map(function ($video) {
                $video->is_liked = false;
                $video->is_bookmarked = false;
                return $video;
            });
        }

        return response()->json([
            'users' => $users,
            'videos' => $videos,
        ]);
    }
}
