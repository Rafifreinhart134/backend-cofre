<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    /**
     * Fetch approved videos untuk feed dengan smart algorithm
     * Algorithm: Engagement Score + Recency Boost (optimized for large datasets)
     * Score = (likes * 3) + (comments * 2) + (views * 0.5) + (recency_boost)
     * Optimizations:
     * 1. All calculations done at database level using raw SQL
     * 2. Redis caching for popular videos (5 minute TTL)
     * 3. Cache key includes page number for proper pagination
     *
     * To enable Redis caching: Set CACHE_STORE=redis in .env
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $currentUserId = $currentUser ? $currentUser->id : null;

        $page = $request->input('page', 1);
        $perPage = 20;

        // Cache key includes page number
        $cacheKey = "video_feed_page_{$page}";
        $cacheTTL = 300; // 5 minutes

        // Try to get from cache, fall back to database query
        $videos = Cache::remember($cacheKey, $cacheTTL, function () use ($page, $perPage) {
            // Database-level calculation and sorting using subquery
            // This is MUCH more efficient than loading all videos into memory

            // Get database driver to use appropriate SQL functions
            $driver = \DB::getDriverName();

            // Build recency boost formula based on database type
            if ($driver === 'sqlite') {
                // SQLite: use MAX and julianday for date calculations
                $recencyBoost = 'MAX(0, 100 - CAST(((julianday("now") - julianday(videos.created_at)) * 24) AS INTEGER))';
            } else {
                // MySQL/PostgreSQL: use GREATEST and TIMESTAMPDIFF
                $recencyBoost = 'GREATEST(0, 100 - TIMESTAMPDIFF(HOUR, videos.created_at, NOW()))';
            }

            return Video::select([
                    'videos.*',
                    \DB::raw("(
                        (SELECT COUNT(*) FROM likes WHERE likes.video_id = videos.id) * 3 +
                        (SELECT COUNT(*) FROM comments WHERE comments.video_id = videos.id) * 2 +
                        (SELECT COUNT(*) FROM views WHERE views.video_id = videos.id) * 0.5 +
                        {$recencyBoost}
                    ) as recommendation_score"),
                    \DB::raw('(SELECT COUNT(*) FROM likes WHERE likes.video_id = videos.id) as likes_count'),
                    \DB::raw('(SELECT COUNT(*) FROM comments WHERE comments.video_id = videos.id) as comments_count'),
                    \DB::raw('(SELECT COUNT(*) FROM views WHERE views.video_id = videos.id) as views_count')
                ])
                ->where('status', 'approved')
                ->with([
                    'user:id,name,avatar_url',
                    'tags' => function($query) {
                        $query->where('status', 'approved')
                              ->with('taggedUser:id,name,avatar_url');
                    }
                ])
                ->orderByDesc('recommendation_score')
                ->paginate($perPage, ['*'], 'page', $page);
        });

        // Get paginated collection
        $paginatedVideos = $videos->getCollection();

        // Optimize: Load all likes, bookmarks, and follows in single queries to avoid N+1
        if ($currentUserId && !$paginatedVideos->isEmpty()) {
            $videoIds = $paginatedVideos->pluck('id')->toArray();
            $userIds = $paginatedVideos->pluck('user_id')->unique()->toArray();

            // Get all likes, bookmarks, and follows in single queries
            $likedVideoIds = \App\Models\Like::where('user_id', $currentUserId)
                ->whereIn('video_id', $videoIds)
                ->pluck('video_id')
                ->toArray();

            $bookmarkedVideoIds = \App\Models\Bookmark::where('user_id', $currentUserId)
                ->whereIn('video_id', $videoIds)
                ->pluck('video_id')
                ->toArray();

            $followingUserIds = \App\Models\Follow::where('follower_id', $currentUserId)
                ->whereIn('following_id', $userIds)
                ->pluck('following_id')
                ->toArray();

            // Add flags to videos
            $paginatedVideos = $paginatedVideos->map(function ($video) use ($likedVideoIds, $bookmarkedVideoIds, $followingUserIds, $currentUserId) {
                $video->is_liked = in_array($video->id, $likedVideoIds);
                $video->is_bookmarked = in_array($video->id, $bookmarkedVideoIds);
                $video->is_following = $video->user_id !== $currentUserId && in_array($video->user_id, $followingUserIds);
                return $video;
            });
        } else {
            // No user logged in - set all to false
            $paginatedVideos = $paginatedVideos->map(function ($video) {
                $video->is_liked = false;
                $video->is_bookmarked = false;
                $video->is_following = false;
                return $video;
            });
        }

        return response()->json($videos);
    }

    public function upload(Request $request)
    {
        // Validasi input - support both images and videos
        $request->validate([
            'video' => 'required|file|mimes:mp4,mov,avi,jpeg,jpg,png|max:102400', // max 100MB for video, images
            'thumbnail' => 'required|file|mimes:jpeg,jpg,png|max:5120', // max 5MB
            'menu_data' => 'required|json',
        ]);

        // Ambil user yang terautentikasi
        $user = $request->user();

        // Upload media (video or image) ke R2
        $mediaFile = $request->file('video');
        $timestamp = time();
        $mediaFileName = $timestamp . '_' . $mediaFile->getClientOriginalName();

        // Determine media type
        $menuData = json_decode($request->menu_data, true);
        $mediaType = $menuData['media_type'] ?? 'video'; // default to video for backward compatibility

        try {
            // DEADLINE MODE: Local Public Storage (Simple & Fast!)

            // Upload video/image to local public storage
            $videoPath = $mediaFile->store('videos', 'public');

            if (!$videoPath) {
                return response()->json([
                    'message' => 'Gagal mengupload media ke storage. Silakan coba lagi.',
                    'error' => 'Media upload failed'
                ], 500);
            }

            // Generate full public URL using url() helper
            $videoUrl = url('storage/' . $videoPath);

            // Upload thumbnail to local public storage
            $thumbnailFile = $request->file('thumbnail');
            $thumbnailPath = $thumbnailFile->store('thumbnails', 'public');

            if (!$thumbnailPath) {
                // Rollback: delete uploaded video
                Storage::disk('public')->delete($videoPath);
                return response()->json([
                    'message' => 'Gagal mengupload thumbnail. Silakan coba lagi.',
                    'error' => 'Thumbnail upload failed'
                ], 500);
            }

            // Generate full public URL for thumbnail
            $thumbnailUrl = url('storage/' . $thumbnailPath);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengupload media: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }

        // DEADLINE MODE: FORCE AUTO-APPROVE - Video langsung muncul di feed
        // Status HARDCODED ke 'approved' agar video langsung tampil tanpa moderasi

        $video = Video::create([
            'user_id' => $user->id,
            's3_url' => $videoUrl,  // Local storage URL (we keep column name for compatibility)
            'thumbnail_url' => $thumbnailUrl,
            'menu_data' => $request->menu_data,
            'status' => 'approved', // HARDCODE INI AGAR LANGSUNG MUNCUL
        ]);

        // Clear video feed cache when new video is uploaded
        $this->clearVideoFeedCache();

        $successMessage = $mediaType === 'image' ? 'Foto berhasil diupload!' : 'Video berhasil diupload!';

        return response()->json([
            'message' => $successMessage,
            'video' => $video,
        ], 201);
    }

    /**
     * Get current user's videos
     */
    public function myVideos(Request $request)
    {
        $user = $request->user();
        $videos = $user->videos()
            ->with([
                'user:id,name,avatar_url',
                'tags' => function($query) {
                    $query->where('status', 'approved')
                          ->with('taggedUser:id,name,avatar_url');
                }
            ])
            ->latest()
            ->paginate(20);

        // Add is_liked, is_bookmarked, and is_following for each video
        $videos->getCollection()->transform(function ($video) use ($user) {
            $video->is_liked = $video->isLikedBy($user->id);
            $video->is_bookmarked = $video->isBookmarkedBy($user->id);
            $video->is_following = false; // User's own videos
            return $video;
        });

        return response()->json($videos);
    }

    /**
     * Get user's reposted videos
     */
    public function myReposts(Request $request)
    {
        $user = $request->user();

        // Get reposts with video and original user data
        $reposts = \App\Models\Repost::where('user_id', $user->id)
            ->with(['video.user:id,name,avatar_url'])
            ->latest()
            ->paginate(20);

        // Transform to include video data with repost info
        $videos = $reposts->getCollection()->map(function ($repost) use ($user) {
            $video = $repost->video;
            if ($video) {
                $video->is_liked = $video->isLikedBy($user->id);
                $video->is_bookmarked = $video->isBookmarkedBy($user->id);
                $video->is_following = $video->user_id !== $user->id;
                $video->is_reposted = true;
                $video->reposted_at = $repost->created_at;
                $video->original_user = $video->user; // Keep original creator info
            }
            return $video;
        })->filter(); // Remove null videos

        return response()->json([
            'data' => $videos,
            'current_page' => $reposts->currentPage(),
            'last_page' => $reposts->lastPage(),
            'per_page' => $reposts->perPage(),
            'total' => $reposts->total(),
        ]);
    }

    /**
     * Get videos from users that current user is following
     */
    public function following(Request $request)
    {
        $currentUser = $request->user();
        $currentUserId = $currentUser->id;

        // Get IDs of users that current user is following
        $followingUserIds = $currentUser->following()->pluck('users.id')->toArray();

        // If not following anyone, return empty
        if (empty($followingUserIds)) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'per_page' => 20,
                'total' => 0,
            ]);
        }

        // Get videos from followed users
        $videos = Video::whereIn('user_id', $followingUserIds)
            ->where('status', 'approved')
            ->with([
                'user:id,name,avatar_url',
                'tags' => function($query) {
                    $query->where('status', 'approved')
                          ->with('taggedUser:id,name,avatar_url');
                }
            ])
            ->withCount(['likes', 'comments', 'views'])
            ->latest()
            ->paginate(20);

        // Optimize: Get all likes and bookmarks in single queries
        if (!$videos->isEmpty()) {
            $videoIds = $videos->pluck('id')->toArray();

            $likedVideoIds = \App\Models\Like::where('user_id', $currentUserId)
                ->whereIn('video_id', $videoIds)
                ->pluck('video_id')
                ->toArray();

            $bookmarkedVideoIds = \App\Models\Bookmark::where('user_id', $currentUserId)
                ->whereIn('video_id', $videoIds)
                ->pluck('video_id')
                ->toArray();

            $videos->getCollection()->transform(function ($video) use ($likedVideoIds, $bookmarkedVideoIds, $followingUserIds) {
                $video->is_liked = in_array($video->id, $likedVideoIds);
                $video->is_bookmarked = in_array($video->id, $bookmarkedVideoIds);
                $video->is_following = in_array($video->user_id, $followingUserIds);
                return $video;
            });
        }

        return response()->json($videos);
    }

    /**
     * Record a view on a video
     */
    public function recordView(Request $request, $videoId)
    {
        $video = Video::findOrFail($videoId);
        $user = $request->user();

        \App\Models\View::create([
            'user_id' => $user ? $user->id : null,
            'video_id' => $video->id,
            'ip_address' => $request->ip(),
            'viewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'View recorded',
        ]);
    }

    /**
     * Search videos
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $currentUser = $request->user();
        $currentUserId = $currentUser ? $currentUser->id : null;

        $videos = Video::where(function ($q) use ($query) {
                $q->where('menu_data', 'like', '%' . $query . '%')
                  ->orWhereHas('user', function ($userQuery) use ($query) {
                      $userQuery->where('name', 'like', '%' . $query . '%');
                  });
            })
            ->where('status', 'approved')
            ->with('user:id,name')
            ->latest()
            ->paginate(20);

        // Add is_liked, is_bookmarked, and is_following for each video
        $videos->getCollection()->transform(function ($video) use ($currentUser, $currentUserId) {
            $video->is_liked = $video->isLikedBy($currentUserId);
            $video->is_bookmarked = $video->isBookmarkedBy($currentUserId);
            // Check if current user is following the video creator
            $video->is_following = $currentUser && $video->user_id !== $currentUserId
                ? $currentUser->isFollowing($video->user_id)
                : false;
            return $video;
        });

        return response()->json($videos);
    }

    /**
     * Delete a video
     */
    public function destroy(Request $request, $videoId)
    {
        $video = Video::findOrFail($videoId);
        $user = $request->user();

        // Only the owner can delete their video
        if ($video->user_id !== $user->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk menghapus video ini',
            ], 403);
        }

        try {
            // Delete video and thumbnail from R2 storage
            if ($video->s3_url) {
                // Extract path from full URL
                $videoPath = parse_url($video->s3_url, PHP_URL_PATH);
                $videoPath = ltrim($videoPath, '/');
                Storage::disk('s3')->delete($videoPath);
            }

            if ($video->thumbnail_url) {
                $thumbnailPath = parse_url($video->thumbnail_url, PHP_URL_PATH);
                $thumbnailPath = ltrim($thumbnailPath, '/');
                Storage::disk('s3')->delete($thumbnailPath);
            }

            // Delete video record from database (cascade will delete related records)
            $video->delete();

            // Clear video feed cache when video is deleted
            $this->clearVideoFeedCache();

            return response()->json([
                'message' => 'Video berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus video: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Repost a video
     */
    public function repost(Request $request, $videoId)
    {
        $video = Video::findOrFail($videoId);
        $user = $request->user();

        // Check if user already reposted this video
        $existingRepost = \App\Models\Repost::where('user_id', $user->id)
            ->where('original_video_id', $video->id)
            ->first();

        if ($existingRepost) {
            return response()->json([
                'message' => 'Anda sudah memposting ulang video ini',
            ], 400);
        }

        // Create repost record
        \App\Models\Repost::create([
            'user_id' => $user->id,
            'original_video_id' => $video->id,
        ]);

        return response()->json([
            'message' => 'Video berhasil diposting ulang',
        ]);
    }

    /**
     * Mark video as not interested
     */
    public function notInterested(Request $request, $videoId)
    {
        $video = Video::findOrFail($videoId);
        $user = $request->user();

        // Create or update preference
        \App\Models\UserPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'video_id' => $video->id,
            ],
            [
                'not_interested' => true,
            ]
        );

        return response()->json([
            'message' => 'Preferensi Anda telah disimpan',
        ]);
    }

    /**
     * Report a video
     */
    public function report(Request $request, $videoId)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $video = Video::findOrFail($videoId);
        $user = $request->user();

        // Check if user already reported this video
        $existingReport = \App\Models\Report::where('user_id', $user->id)
            ->where('video_id', $video->id)
            ->first();

        if ($existingReport) {
            return response()->json([
                'message' => 'Anda sudah melaporkan video ini',
            ], 400);
        }

        // Create report
        \App\Models\Report::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Laporan Anda telah dikirim',
        ]);
    }

    /**
     * Share video to a friend
     */
    public function shareToFriend(Request $request, $videoId)
    {
        $request->validate([
            'friend_id' => 'required|integer|exists:users,id',
        ]);

        $video = Video::findOrFail($videoId);
        $user = $request->user();
        $friendId = $request->friend_id;

        // Create share record
        \App\Models\Share::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'recipient_id' => $friendId,
        ]);

        // Create notification for the friend (optional)
        // You can implement this if you have a notifications system

        return response()->json([
            'message' => 'Video berhasil dibagikan',
        ]);
    }

    /**
     * Clear video feed cache for all pages
     * This should be called when videos are created, updated, or deleted
     */
    private function clearVideoFeedCache(): void
    {
        // Clear cache for first 10 pages (covers most use cases)
        // In production, you might want to use cache tags for better management
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("video_feed_page_{$page}");
        }
    }
}
