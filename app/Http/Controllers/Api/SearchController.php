<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Video;
use App\Models\SearchLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // TODO: Re-enable logging after troubleshooting
        // try {
        //     SearchLog::logSearch($query, $currentUserId, 'general', $request->ip());
        // } catch (\Exception $e) {
        //     Log::error('Search logging failed: ' . $e->getMessage());
        // }

        return response()->json([
            'users' => $users,
            'videos' => $videos,
        ]);
    }

    /**
     * Get trending searches
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTrending(Request $request)
    {
        try {
            $days = $request->input('days', 7); // Default 7 days
            $limit = $request->input('limit', 10); // Default 10 results

            // Validate inputs
            $days = min(max($days, 1), 30); // Between 1 and 30 days
            $limit = min(max($limit, 5), 20); // Between 5 and 20 results

            $trending = SearchLog::getTrending($days, $limit);

            // Fallback to default if no trending searches
            if ($trending->isEmpty()) {
                $trending = collect([
                    ['query' => 'Nasi Goreng', 'count' => 0],
                    ['query' => 'Ayam Geprek', 'count' => 0],
                    ['query' => 'Dessert Box', 'count' => 0],
                    ['query' => 'Resep Murah', 'count' => 0],
                    ['query' => 'Street Food Jakarta', 'count' => 0],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $trending,
                'period_days' => $days,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting trending searches: ' . $e->getMessage());

            // Return fallback data on error
            return response()->json([
                'success' => true,
                'data' => [
                    ['query' => 'Nasi Goreng', 'count' => 0],
                    ['query' => 'Ayam Geprek', 'count' => 0],
                    ['query' => 'Dessert Box', 'count' => 0],
                    ['query' => 'Resep Murah', 'count' => 0],
                    ['query' => 'Street Food Jakarta', 'count' => 0],
                ],
                'period_days' => 7,
            ]);
        }
    }

    /**
     * Log a search query
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logSearch(Request $request)
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|max:255',
                'type' => 'nullable|string|in:general,user,video',
            ]);

            $userId = auth()->id();
            $ipAddress = $request->ip();

            SearchLog::logSearch(
                $validated['query'],
                $userId,
                $validated['type'] ?? 'general',
                $ipAddress
            );

            return response()->json([
                'success' => true,
                'message' => 'Search logged successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging search: ' . $e->getMessage());

            // Don't fail the request if logging fails
            return response()->json([
                'success' => true,
                'message' => 'Search logged (silent fail)',
            ]);
        }
    }

    /**
     * Get user's recent searches
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecentSearches(Request $request)
    {
        try {
            $userId = auth()->id();
            $limit = $request->input('limit', 10);
            $limit = min(max($limit, 5), 20); // Between 5 and 20

            if (!$userId) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $recent = SearchLog::getUserRecent($userId, $limit);

            return response()->json([
                'success' => true,
                'data' => $recent,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting recent searches: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }
    }

    /**
     * Clear user's search history
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearHistory()
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            SearchLog::where('user_id', $userId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Search history cleared successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error clearing search history: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear search history',
            ], 500);
        }
    }
}
