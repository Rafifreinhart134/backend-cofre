<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\ModerationController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\BadgeApplicationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth routes with rate limiting to prevent brute force attacks
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1'); // 5 attempts per minute
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 5 attempts per minute

// Email Verification routes
Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])->middleware(['auth:sanctum', 'throttle:3,1']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('throttle:10,1')->name('verification.verify');

// Password Reset routes
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');

// TEST ONLY: AI Scan without auth (REMOVE IN PRODUCTION)
Route::post('/ai/scan-test', [AiController::class, 'scan']);

// ========== PUBLIC ENDPOINTS - Guest dapat akses (FIX APPLIED) ==========
Route::get('/videos', [VideoController::class, 'index'])->middleware('throttle:60,1'); // 60 requests per minute
Route::get('/videos/search', [VideoController::class, 'search'])->middleware('throttle:30,1'); // 30 searches per minute
Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:30,1'); // 30 searches per minute
// ========================================================================

Route::middleware('auth:sanctum')->group(function () {
    // User
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Videos (authenticated only)
    Route::get('/videos/my-videos', [VideoController::class, 'myVideos'])->middleware('throttle:60,1');
    Route::get('/videos/my-reposts', [VideoController::class, 'myReposts'])->middleware('throttle:60,1');
    Route::get('/videos/following', [VideoController::class, 'following'])->middleware('throttle:60,1'); // Get videos from followed users
    Route::post('/videos/upload', [VideoController::class, 'upload'])->middleware('throttle:10,60'); // 10 uploads per hour
    Route::post('/videos/{video}/view', [VideoController::class, 'recordView'])->middleware('throttle:120,1'); // 120 views per minute
    Route::delete('/videos/{video}', [VideoController::class, 'destroy'])->middleware('throttle:10,1');

    // Likes (rate limited to prevent spam)
    Route::post('/videos/{video}/like', [LikeController::class, 'toggle'])->middleware('throttle:60,1'); // 60 likes per minute

    // Comments (rate limited to prevent spam)
    Route::get('/videos/{video}/comments', [CommentController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/videos/{video}/comments', [CommentController::class, 'store'])->middleware('throttle:20,1'); // 20 comments per minute
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->middleware('throttle:30,1');

    // Bookmarks
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/videos/{video}/bookmark', [BookmarkController::class, 'toggle'])->middleware('throttle:60,1');

    // Follows (rate limited to prevent spam)
    Route::post('/users/{user}/follow', [FollowController::class, 'toggle'])->middleware('throttle:30,1'); // 30 follows per minute

    // User Profiles
    Route::get('/users/recommended', [UserController::class, 'recommendedAccounts'])->middleware('throttle:60,1'); // Get recommended accounts with videos
    Route::get('/users/{user}/profile', [UserController::class, 'show'])->middleware('throttle:60,1');
    Route::get('/users/{user}/videos', [UserController::class, 'videos'])->middleware('throttle:60,1');
    Route::put('/user/profile', [UserController::class, 'updateProfile'])->middleware('throttle:100,1'); // 100 updates per minute (dev mode)
    // DEVELOPMENT: No rate limit for avatar uploads during testing (add throttle in production)
    Route::post('/user/avatar', [UserController::class, 'uploadAvatar']); // No rate limit (dev mode)
    Route::post('/user/change-password', [UserController::class, 'changePassword'])->middleware('throttle:5,60'); // 5 password changes per hour
    Route::delete('/user/account', [UserController::class, 'deleteAccount'])->middleware('throttle:3,60'); // 3 deletion attempts per hour

    // Badge Application Routes
    // DEVELOPMENT: Increased throttle for testing (change back to 3,1440 in production)
    Route::post('/badge/apply', [BadgeApplicationController::class, 'apply'])->middleware('throttle:60,1'); // 60 applications per minute (dev mode)
    Route::get('/badge/status', [BadgeApplicationController::class, 'status'])->middleware('throttle:60,1'); // Get badge status
    Route::post('/badge/reapply', [BadgeApplicationController::class, 'reapply'])->middleware('throttle:60,1'); // 60 reapplications per minute (dev mode)
    Route::patch('/profile/badge-visibility', [BadgeApplicationController::class, 'toggleVisibility'])->middleware('throttle:30,1'); // Toggle badge visibility

    // Share & Social Actions
    Route::post('/videos/{video}/repost', [VideoController::class, 'repost']);
    Route::post('/videos/{video}/not-interested', [VideoController::class, 'notInterested']);
    Route::post('/videos/{video}/report', [VideoController::class, 'report']);
    Route::post('/videos/{video}/share', [VideoController::class, 'shareToFriend']);

    // Friends
    Route::get('/friends', [UserController::class, 'friends']);

    // Notifications
    Route::get('/notifications', [UserController::class, 'notifications']);
    Route::post('/notifications/{notification}/read', [UserController::class, 'markNotificationAsRead']);
    Route::post('/notifications/read-all', [UserController::class, 'markAllNotificationsAsRead']);

    // AI Food Scanner (Hackathon Feature)
    // Development: 100 scans per minute (increase for testing)
    // Production: Change to throttle:20,1 (20 scans per minute)
    Route::post('/ai/scan', [AiController::class, 'scan'])->middleware('throttle:100,1'); // 100 scans per minute for development

    // Stories
    Route::get('/stories', [StoryController::class, 'index'])->middleware('throttle:60,1'); // Get all active stories
    Route::get('/stories/debug', [StoryController::class, 'debug'])->middleware('throttle:60,1'); // DEBUG: Get all stories including expired
    Route::get('/stories/my-stories', [StoryController::class, 'myStories'])->middleware('throttle:60,1'); // Get user's own stories
    Route::get('/stories/archived', [StoryController::class, 'archived'])->middleware('throttle:60,1'); // Get archived stories
    Route::post('/stories/upload', [StoryController::class, 'upload'])->middleware('throttle:20,60'); // 20 uploads per hour
    Route::post('/stories/{story}/view', [StoryController::class, 'markAsViewed'])->middleware('throttle:120,1'); // Mark as viewed
    Route::get('/stories/{story}/viewers', [StoryController::class, 'viewers'])->middleware('throttle:60,1'); // Get story viewers
    Route::post('/stories/{story}/archive', [StoryController::class, 'archive'])->middleware('throttle:30,1'); // Archive story
    Route::post('/stories/{story}/unarchive', [StoryController::class, 'unarchive'])->middleware('throttle:30,1'); // Unarchive story
    Route::delete('/stories/{story}', [StoryController::class, 'destroy'])->middleware('throttle:30,1'); // Delete story
    Route::post('/stories/{story}/reply', [StoryController::class, 'reply'])->middleware('throttle:60,1'); // Reply to story
    Route::post('/stories/{story}/react', [StoryController::class, 'react'])->middleware('throttle:120,1'); // React to story with emoji
    Route::post('/stories/{story}/share', [StoryController::class, 'share'])->middleware('throttle:30,1'); // Share/repost story
    Route::post('/stories/{story}/report', [StoryController::class, 'report'])->middleware('throttle:10,1'); // Report story (10 reports per minute)

    // Story Highlights Routes
    Route::get('/highlights', [App\Http\Controllers\Api\HighlightController::class, 'index'])->middleware('throttle:60,1'); // Get user's highlights
    Route::get('/highlights/{highlight}', [App\Http\Controllers\Api\HighlightController::class, 'show'])->middleware('throttle:60,1'); // Get specific highlight
    Route::post('/highlights', [App\Http\Controllers\Api\HighlightController::class, 'store'])->middleware('throttle:30,1'); // Create highlight
    Route::put('/highlights/{highlight}', [App\Http\Controllers\Api\HighlightController::class, 'update'])->middleware('throttle:30,1'); // Update highlight
    Route::delete('/highlights/{highlight}', [App\Http\Controllers\Api\HighlightController::class, 'destroy'])->middleware('throttle:30,1'); // Delete highlight
    Route::post('/highlights/{highlight}/stories', [App\Http\Controllers\Api\HighlightController::class, 'addStory'])->middleware('throttle:60,1'); // Add story to highlight
    Route::delete('/highlights/{highlight}/stories/{story}', [App\Http\Controllers\Api\HighlightController::class, 'removeStory'])->middleware('throttle:60,1'); // Remove story from highlight
    Route::post('/highlights/reorder', [App\Http\Controllers\Api\HighlightController::class, 'reorder'])->middleware('throttle:30,1'); // Reorder highlights

    // Admin - Video Moderation (requires admin role)
    Route::get('/admin/moderation/videos', [ModerationController::class, 'getPendingVideos'])->middleware('throttle:60,1');
    Route::post('/admin/moderation/videos/{video}/approve', [ModerationController::class, 'approveVideo'])->middleware('throttle:30,1');
    Route::post('/admin/moderation/videos/{video}/reject', [ModerationController::class, 'rejectVideo'])->middleware('throttle:30,1');
    Route::get('/admin/moderation/stats', [ModerationController::class, 'getModerationStats'])->middleware('throttle:60,1');

    // Device Tokens for Push Notifications
    Route::post('/device-tokens/register', [App\Http\Controllers\Api\DeviceTokenController::class, 'register'])->middleware('throttle:60,1');
    Route::post('/device-tokens/remove', [App\Http\Controllers\Api\DeviceTokenController::class, 'remove'])->middleware('throttle:60,1');
    Route::get('/device-tokens', [App\Http\Controllers\Api\DeviceTokenController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/device-tokens/deactivate', [App\Http\Controllers\Api\DeviceTokenController::class, 'deactivate'])->middleware('throttle:60,1');

    // Settings Routes
    Route::get('/settings', [SettingsController::class, 'getSettings'])->middleware('throttle:60,1'); // Get all settings
    Route::post('/settings/privacy', [SettingsController::class, 'updatePrivacy'])->middleware('throttle:30,1'); // Update privacy
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotifications'])->middleware('throttle:30,1'); // Update notification settings
    Route::post('/settings/language', [SettingsController::class, 'updateLanguage'])->middleware('throttle:30,1'); // Update language
    Route::post('/users/{user}/block', [SettingsController::class, 'blockUser'])->middleware('throttle:30,1'); // Block user
    Route::delete('/users/{user}/unblock', [SettingsController::class, 'unblockUser'])->middleware('throttle:30,1'); // Unblock user
    Route::get('/users/blocked', [SettingsController::class, 'getBlockedUsers'])->middleware('throttle:60,1'); // Get blocked users
    Route::post('/settings/cache/clear', [SettingsController::class, 'clearCache'])->middleware('throttle:10,60'); // Clear cache (10 per hour)
    Route::get('/settings/storage', [SettingsController::class, 'getStorageInfo'])->middleware('throttle:60,1'); // Get storage info
    Route::get('/settings/about', [SettingsController::class, 'getAppInfo'])->middleware('throttle:60,1'); // Get app info

    // User Tagging Routes
    Route::post('/videos/{video}/tag', [App\Http\Controllers\Api\TagController::class, 'tagUsersInVideo'])->middleware('throttle:30,1'); // Tag users in video
    Route::post('/comments/{comment}/tag', [App\Http\Controllers\Api\TagController::class, 'tagUserInComment'])->middleware('throttle:60,1'); // Tag user in comment
    Route::get('/tags/pending', [App\Http\Controllers\Api\TagController::class, 'getPendingTags'])->middleware('throttle:60,1'); // Get pending tags
    Route::post('/tags/{tag}/approve', [App\Http\Controllers\Api\TagController::class, 'approveTag'])->middleware('throttle:30,1'); // Approve tag
    Route::post('/tags/{tag}/reject', [App\Http\Controllers\Api\TagController::class, 'rejectTag'])->middleware('throttle:30,1'); // Reject tag
    Route::delete('/tags/{tag}', [App\Http\Controllers\Api\TagController::class, 'removeTag'])->middleware('throttle:30,1'); // Remove tag
    Route::get('/users/{user}/tagged-videos', [App\Http\Controllers\Api\TagController::class, 'getTaggedVideos'])->middleware('throttle:60,1'); // Get tagged videos
    Route::get('/users/search-for-tagging', [App\Http\Controllers\Api\TagController::class, 'searchUsersForTagging'])->middleware('throttle:120,1'); // Search users for tagging (autocomplete)

    // Playlist Routes
    Route::get('/playlists', [App\Http\Controllers\Api\PlaylistController::class, 'index'])->middleware('throttle:60,1'); // Get all user playlists
    Route::post('/playlists', [App\Http\Controllers\Api\PlaylistController::class, 'store'])->middleware('throttle:30,1'); // Create playlist
    Route::get('/playlists/{id}', [App\Http\Controllers\Api\PlaylistController::class, 'show'])->middleware('throttle:60,1'); // Get playlist details
    Route::put('/playlists/{id}', [App\Http\Controllers\Api\PlaylistController::class, 'update'])->middleware('throttle:30,1'); // Update playlist
    Route::delete('/playlists/{id}', [App\Http\Controllers\Api\PlaylistController::class, 'destroy'])->middleware('throttle:30,1'); // Delete playlist
    Route::post('/playlists/{playlist}/videos', [App\Http\Controllers\Api\PlaylistController::class, 'addVideo'])->middleware('throttle:60,1'); // Add video to playlist
    Route::delete('/playlists/{playlist}/videos/{video}', [App\Http\Controllers\Api\PlaylistController::class, 'removeVideo'])->middleware('throttle:60,1'); // Remove video from playlist
    Route::get('/users/{userId}/playlists', [App\Http\Controllers\Api\PlaylistController::class, 'getUserPlaylists'])->middleware('throttle:60,1'); // Get user's public playlists
});
