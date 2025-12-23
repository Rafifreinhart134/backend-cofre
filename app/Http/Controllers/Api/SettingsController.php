<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class SettingsController extends Controller
{
    /**
     * Get user settings
     */
    public function getSettings(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'settings' => [
                'account_private' => $user->account_private ?? false,
                'language' => $user->language ?? 'id',
                'notification_settings' => $user->notification_settings ?? [
                    'likes' => true,
                    'comments' => true,
                    'follows' => true,
                    'mentions' => true,
                    'reposts' => true,
                ],
            ],
        ]);
    }

    /**
     * Update account privacy
     */
    public function updatePrivacy(Request $request)
    {
        $request->validate([
            'account_private' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->account_private = $request->account_private;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Privacy settings updated successfully',
            'account_private' => $user->account_private,
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateNotifications(Request $request)
    {
        $request->validate([
            'likes' => 'boolean',
            'comments' => 'boolean',
            'follows' => 'boolean',
            'mentions' => 'boolean',
            'reposts' => 'boolean',
        ]);

        $user = $request->user();

        $notificationSettings = [
            'likes' => $request->input('likes', true),
            'comments' => $request->input('comments', true),
            'follows' => $request->input('follows', true),
            'mentions' => $request->input('mentions', true),
            'reposts' => $request->input('reposts', true),
        ];

        $user->notification_settings = $notificationSettings;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully',
            'notification_settings' => $notificationSettings,
        ]);
    }

    /**
     * Update language
     */
    public function updateLanguage(Request $request)
    {
        $request->validate([
            'language' => 'required|in:id,en',
        ]);

        $user = $request->user();
        $user->language = $request->language;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Language updated successfully',
            'language' => $user->language,
        ]);
    }

    /**
     * Block a user
     */
    public function blockUser(Request $request, $userId)
    {
        $user = $request->user();

        // Can't block yourself
        if ($user->id == $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot block yourself',
            ], 400);
        }

        // Check if already blocked
        if ($user->hasBlocked($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'User is already blocked',
            ], 400);
        }

        // Block the user
        $user->blockedUsers()->attach($userId);

        // Also unfollow if following
        if ($user->isFollowing($userId)) {
            $user->following()->detach($userId);
        }

        return response()->json([
            'success' => true,
            'message' => 'User blocked successfully',
        ]);
    }

    /**
     * Unblock a user
     */
    public function unblockUser(Request $request, $userId)
    {
        $user = $request->user();

        if (!$user->hasBlocked($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'User is not blocked',
            ], 400);
        }

        $user->blockedUsers()->detach($userId);

        return response()->json([
            'success' => true,
            'message' => 'User unblocked successfully',
        ]);
    }

    /**
     * Get list of blocked users
     */
    public function getBlockedUsers(Request $request)
    {
        $user = $request->user();

        $blockedUsers = $user->blockedUsers()
            ->select('users.id', 'users.name', 'users.avatar_url')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $blockedUsers,
        ]);
    }

    /**
     * Clear app cache
     */
    public function clearCache(Request $request)
    {
        try {
            // Clear Laravel cache
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
            ], 500);
        }
    }

    /**
     * Get storage info
     */
    public function getStorageInfo(Request $request)
    {
        $user = $request->user();

        // Calculate user's video storage
        $videos = $user->videos()->get();
        $totalSize = 0;

        foreach ($videos as $video) {
            if ($video->s3_url) {
                // Extract path from URL - handle both full URLs and relative paths
                $path = $video->s3_url;

                // Remove base URL if present
                if (strpos($path, 'http') === 0) {
                    $path = str_replace(url('storage/'), '', $path);
                }

                // Remove leading 'storage/' if present
                $path = ltrim($path, '/');
                $path = str_replace('storage/', '', $path);

                $fullPath = storage_path('app/public/' . $path);

                if (File::exists($fullPath)) {
                    $totalSize += File::size($fullPath);
                }
            }
        }

        // Convert to MB
        $totalSizeMB = round($totalSize / 1024 / 1024, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'total_videos' => $videos->count(),
                'total_size_mb' => $totalSizeMB,
                'total_size_bytes' => $totalSize,
            ],
        ]);
    }

    /**
     * Get app info
     */
    public function getAppInfo(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'app_name' => 'Covre',
                'version' => '1.0.0',
                'build' => '2025.12.05',
                'backend_version' => app()->version(),
            ],
        ]);
    }
}
