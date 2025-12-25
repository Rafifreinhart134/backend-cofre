<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get user profile by ID
     */
    public function show(Request $request, $userId)
    {
        $currentUser = $request->user();
        $user = User::findOrFail($userId);

        // Get user's approved videos
        $videos = Video::where('user_id', $userId)
            ->where('status', 'approved')
            ->with([
                'user:id,name,avatar_url',
                'tags' => function($query) {
                    $query->where('status', 'approved')
                          ->with('taggedUser:id,name,avatar_url');
                }
            ])
            ->latest()
            ->paginate(20);

        // Calculate stats
        $totalVideos = Video::where('user_id', $userId)
            ->where('status', 'approved')
            ->count();

        $totalLikes = Video::where('user_id', $userId)
            ->where('status', 'approved')
            ->withCount('likes')
            ->get()
            ->sum('likes_count');

        $totalViews = Video::where('user_id', $userId)
            ->where('status', 'approved')
            ->withCount('views')
            ->get()
            ->sum('views_count');

        // Check if current user follows this user
        $isFollowing = false;
        if ($currentUser) {
            $isFollowing = $currentUser->following()->where('following_id', $userId)->exists();
        }

        // Add is_liked and is_bookmarked for each video
        $videos->getCollection()->transform(function ($video) use ($currentUser) {
            if ($currentUser) {
                $video->is_liked = $video->isLikedBy($currentUser->id);
                $video->is_bookmarked = $video->isBookmarkedBy($currentUser->id);
            } else {
                $video->is_liked = false;
                $video->is_bookmarked = false;
            }
            $video->is_following = false; // Not applicable for own videos
            return $video;
        });

        // Load counts efficiently (avoid N+1)
        $user->loadCount(['followers', 'following']);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'bio' => $user->bio,
                'account_type' => $user->account_type ?? 'regular',
                'badge_status' => $user->badge_status,
                'show_badge' => $user->show_badge ?? true,
                'website' => $user->website,
                'followers_count' => $user->followers_count,
                'following_count' => $user->following_count,
                'is_following' => $isFollowing,
            ],
            'stats' => [
                'videos' => $totalVideos,
                'likes' => $totalLikes,
                'views' => $totalViews,
            ],
            'videos' => $videos,
        ]);
    }

    /**
     * Get user's public videos only (for video grid)
     */
    public function videos(Request $request, $userId)
    {
        $currentUser = $request->user();

        $videos = Video::where('user_id', $userId)
            ->where('status', 'approved')
            ->with('user:id,name')
            ->latest()
            ->paginate(20);

        // Add is_liked and is_bookmarked for each video
        $videos->getCollection()->transform(function ($video) use ($currentUser) {
            if ($currentUser) {
                $video->is_liked = $video->isLikedBy($currentUser->id);
                $video->is_bookmarked = $video->isBookmarkedBy($currentUser->id);
                $video->is_following = $currentUser->following()->where('following_id', $video->user_id)->exists();
            } else {
                $video->is_liked = false;
                $video->is_bookmarked = false;
                $video->is_following = false;
            }
            return $video;
        });

        return response()->json($videos);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:30|regex:/^[a-zA-Z0-9._]+$/|unique:users,username,' . $user->id,
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'bio' => 'sometimes|string|max:150|nullable',
            'account_type' => 'sometimes|in:regular,creator',
            'website' => 'nullable|url|max:500',
        ]);

        // Check username uniqueness with custom error code
        if ($request->has('username') && $request->username !== $user->username) {
            if (User::where('username', $request->username)->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'USERNAME_EXISTS',
                    'message' => 'Username sudah digunakan',
                ], 422);
            }
        }

        // Check email uniqueness with custom error code
        if ($request->has('email') && $request->email !== $user->email) {
            if (User::where('email', $request->email)->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'EMAIL_EXISTS',
                    'message' => 'Email sudah digunakan',
                ], 422);
            }
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('username')) {
            $user->username = $request->username;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('bio')) {
            $user->bio = $request->bio;
        }

        if ($request->has('website')) {
            $user->website = $request->website;
        }

        if ($request->has('account_type')) {
            // Only allow setting account_type to 'creator' if badge is approved
            if ($request->account_type === 'creator' && $user->badge_status !== 'approved') {
                return response()->json([
                    'message' => 'Anda harus mengajukan dan mendapatkan persetujuan badge creator terlebih dahulu',
                ], 403);
            }
            $user->account_type = $request->account_type;
        }

        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user' => $user,
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string|min:6',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if (!\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Password saat ini salah',
            ], 400);
        }

        // Update password
        $user->password = \Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diubah',
        ]);
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,jpg,png,gif|max:5120', // 5MB max
        ]);

        try {
            // Delete old avatar if exists (local storage)
            if ($user->avatar_url) {
                $oldPath = str_replace('/storage/', '', $user->avatar_url);
                if (\Storage::disk('public')->exists($oldPath)) {
                    \Storage::disk('public')->delete($oldPath);
                }
            }

            // Upload new avatar to local storage
            $file = $request->file('avatar');
            $filename = 'avatars/' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();

            // Store in public disk
            $path = $file->storeAs('avatars', $user->id . '_' . time() . '.' . $file->getClientOriginalExtension(), 'public');

            // Generate full public URL using url() helper
            $url = url('storage/' . $path);

            // Update user avatar_url
            $user->avatar_url = $url;
            $user->save();

            return response()->json([
                'message' => 'Avatar berhasil diupload',
                'avatar_url' => $url,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengupload avatar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete user account
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'password' => 'required|string',
        ]);

        // Verify password
        if (!\Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Password salah',
            ], 400);
        }

        try {
            // Delete user's avatar if exists
            if ($user->avatar_url) {
                $avatarPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $user->avatar_url);
                Storage::disk('s3')->delete($avatarPath);
            }

            // Delete user's videos and related files
            foreach ($user->videos as $video) {
                // Delete video file from S3
                if ($video->s3_url) {
                    $videoPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $video->s3_url);
                    Storage::disk('s3')->delete($videoPath);
                }
                // Delete thumbnail from S3
                if ($video->thumbnail_url) {
                    $thumbPath = str_replace(config('filesystems.disks.s3.url') . '/', '', $video->thumbnail_url);
                    Storage::disk('s3')->delete($thumbPath);
                }
            }

            // Delete all related data (cascade)
            $user->videos()->delete();
            $user->likes()->delete();
            $user->comments()->delete();
            $user->bookmarks()->delete();
            $user->notifications()->delete(); // Delete notifications
            $user->following()->detach();
            $user->followers()->detach();

            // Delete user tokens
            $user->tokens()->delete();

            // Delete user account
            $user->delete();

            return response()->json([
                'message' => 'Akun berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus akun',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's friends (following)
     */
    public function friends(Request $request)
    {
        $user = $request->user();

        $friends = $user->following()
            ->select('users.id', 'users.name', 'users.email')
            ->paginate(20);

        return response()->json($friends);
    }

    /**
     * Get user notifications
     */
    public function notifications(Request $request)
    {
        $user = $request->user();

        $notifications = \App\Models\Notification::where('user_id', $user->id)
            ->with(['fromUser:id,name,avatar_url', 'video:id,thumbnail_url'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Transform notifications to match frontend format
        $notifications->getCollection()->transform(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'user' => $notification->fromUser ? $notification->fromUser->name : 'System',
                'userId' => $notification->from_user_id,
                'from_user_id' => $notification->from_user_id, // For consistency
                'video_id' => $notification->video_id, // For navigation
                'message' => $notification->message,
                'time' => $notification->created_at->diffForHumans(),
                'hasVideo' => $notification->video_id !== null,
                'videoThumbnail' => $notification->video ? $notification->video->thumbnail_url : null,
                'isRead' => $notification->is_read,
                'is_read' => $notification->is_read, // For consistency
                'created_at' => $notification->created_at,
            ];
        });

        return response()->json($notifications);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(Request $request, $notificationId)
    {
        $user = $request->user();

        $notification = \App\Models\Notification::where('id', $notificationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification,
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsAsRead(Request $request)
    {
        $user = $request->user();

        \App\Models\Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Get recommended accounts with their latest videos
     */
    public function recommendedAccounts(Request $request)
    {
        $currentUser = $request->user();
        $currentUserId = $currentUser ? $currentUser->id : null;

        // Get users with most followers, excluding current user
        $users = User::withCount(['followers', 'videos'])
            ->when($currentUserId, function ($query) use ($currentUserId) {
                $query->where('id', '!=', $currentUserId);
            })
            ->whereHas('videos') // Only users with videos
            ->orderBy('followers_count', 'desc')
            ->limit(10)
            ->get();

        // Get following status in batch to avoid N+1
        $followingUserIds = [];
        if ($currentUserId) {
            $userIds = $users->pluck('id')->toArray();
            $followingUserIds = \App\Models\Follow::where('follower_id', $currentUserId)
                ->whereIn('following_id', $userIds)
                ->pluck('following_id')
                ->toArray();
        }

        // Get latest videos for each user
        $recommendedAccounts = $users->map(function ($user) use ($followingUserIds, $currentUserId) {
            // Get latest 3 videos from this user
            $videos = Video::where('user_id', $user->id)
                ->withCount('likes')
                ->latest()
                ->limit(3)
                ->get();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url ?? null,
                'followers_count' => $user->followers_count,
                'is_following' => in_array($user->id, $followingUserIds),
                'videos' => $videos->map(function ($video) {
                    return [
                        'id' => $video->id,
                        'thumbnail_url' => $video->thumbnail_url,
                        's3_url' => $video->s3_url,
                        'likes_count' => $video->likes_count,
                        'menu_data' => $video->menu_data,
                    ];
                }),
            ];
        });

        return response()->json([
            'data' => $recommendedAccounts,
        ]);
    }
}
