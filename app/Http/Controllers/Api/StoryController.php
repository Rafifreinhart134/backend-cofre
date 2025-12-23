<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryView;
use App\Models\StoryReaction;
use App\Models\StoryReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class StoryController extends Controller
{
    /**
     * Get all active stories - PUBLIC (visible to everyone)
     * Stories are properly ordered: grouped by user, unwatched users first
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();

        // Get ALL active stories (public - visible to everyone)
        $allStories = Story::active()
            ->with(['user:id,name,avatar_url'])
            ->orderBy('created_at', 'asc') // Order stories within user chronologically
            ->get();

        // Debug logging
        \Log::info('Stories fetched', [
            'current_user_id' => $currentUser->id,
            'total_active_stories' => $allStories->count(),
            'current_user_stories_count' => $allStories->where('user_id', $currentUser->id)->count(),
            'all_user_ids' => $allStories->pluck('user_id')->unique()->values(),
        ]);

        // Group stories by user
        $groupedStories = $allStories->groupBy('user_id');

        // Organize user groups: unwatched first, then watched
        $userGroups = [];

        foreach ($groupedStories as $userId => $userStories) {
            // Check if user has any unwatched stories
            $hasUnwatched = $userStories->contains(function ($story) use ($currentUser) {
                return !$story->hasViewedBy($currentUser->id);
            });

            // Get the latest story time for this user (for sorting)
            $latestStoryTime = $userStories->max('created_at');

            $userGroups[] = [
                'user_id' => $userId,
                'stories' => $userStories->values(),
                'has_unwatched' => $hasUnwatched,
                'latest_story_time' => $latestStoryTime,
            ];
        }

        // Sort user groups:
        // 1. Users with unwatched stories first
        // 2. Within each group, sort by latest story time (newest first)
        usort($userGroups, function ($a, $b) {
            // Unwatched users come first
            if ($a['has_unwatched'] && !$b['has_unwatched']) {
                return -1;
            }
            if (!$a['has_unwatched'] && $b['has_unwatched']) {
                return 1;
            }
            // Within same watch status, sort by latest story time (newest first)
            return $b['latest_story_time'] <=> $a['latest_story_time'];
        });

        // Flatten the grouped stories back into a single array
        $orderedStories = collect();
        foreach ($userGroups as $group) {
            foreach ($group['stories'] as $story) {
                $story->has_viewed = $story->hasViewedBy($currentUser->id);
                $orderedStories->push($story);
            }
        }

        \Log::info('Stories response', [
            'total_stories_returned' => $orderedStories->count(),
            'story_ids' => $orderedStories->pluck('id'),
        ]);

        return response()->json([
            'success' => true,
            'stories' => $orderedStories->values()
        ]);
    }

    /**
     * DEBUG: Get ALL stories from database (including expired and archived)
     * This endpoint is for debugging only - remove in production
     */
    public function debug(Request $request)
    {
        $currentUser = $request->user();

        // Get ALL stories without any filters
        $allStories = Story::with(['user:id,name,avatar_url'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Separate stories by status
        $activeStories = $allStories->filter(function ($story) {
            return $story->expires_at->isFuture() && !$story->is_archived;
        });

        $expiredStories = $allStories->filter(function ($story) {
            return $story->expires_at->isPast();
        });

        $archivedStories = $allStories->filter(function ($story) {
            return $story->is_archived;
        });

        $currentUserStories = $allStories->where('user_id', $currentUser->id);

        return response()->json([
            'success' => true,
            'debug_info' => [
                'current_user_id' => $currentUser->id,
                'total_stories_in_db' => $allStories->count(),
                'active_stories_count' => $activeStories->count(),
                'expired_stories_count' => $expiredStories->count(),
                'archived_stories_count' => $archivedStories->count(),
                'current_user_stories_count' => $currentUserStories->count(),
                'current_user_active_stories' => $currentUserStories->filter(function ($story) {
                    return $story->expires_at->isFuture() && !$story->is_archived;
                })->count(),
                'current_user_expired_stories' => $currentUserStories->filter(function ($story) {
                    return $story->expires_at->isPast();
                })->count(),
            ],
            'current_user_stories' => $currentUserStories->map(function ($story) {
                return [
                    'id' => $story->id,
                    'media_url' => $story->media_url,
                    'media_type' => $story->media_type,
                    'created_at' => $story->created_at->toIso8601String(),
                    'expires_at' => $story->expires_at->toIso8601String(),
                    'is_expired' => $story->expires_at->isPast(),
                    'is_archived' => $story->is_archived,
                    'hours_since_created' => $story->created_at->diffInHours(now()),
                ];
            })->values(),
        ]);
    }

    /**
     * Get user's own stories (including archived)
     */
    public function myStories(Request $request)
    {
        $currentUser = $request->user();

        $stories = Story::where('user_id', $currentUser->id)
            ->where('expires_at', '>', Carbon::now())
            ->with(['views.viewer:id,name,avatar_url'])
            ->withCount('views')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'stories' => $stories
        ]);
    }

    /**
     * Upload and create a new story
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media' => 'required|file|mimes:jpg,jpeg,png,mp4,mov|max:51200', // Max 50MB
            'media_type' => 'required|in:image,video',
            'duration' => 'nullable|integer|min:1|max:15',
            'caption' => 'nullable|string|max:500',
            'stickers' => 'nullable|json',
            'text_elements' => 'nullable|json',
            'filter' => 'nullable|string',
            'allow_resharing' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $currentUser = $request->user();
            $file = $request->file('media');
            $mediaType = $request->input('media_type');

            // LOCAL STORAGE MODE: Store stories in local public storage (same as videos)

            // Upload media to local public storage
            $mediaPath = $file->store('stories', 'public');

            if (!$mediaPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload media to storage',
                    'error' => 'Media upload failed'
                ], 500);
            }

            // Generate full public URL using url() helper
            $mediaUrl = url('storage/' . $mediaPath);

            // Generate thumbnail for videos
            $thumbnailUrl = null;
            if ($mediaType === 'video') {
                // In production, you might want to generate actual video thumbnail
                // For now, we'll just use the same URL as placeholder
                $thumbnailUrl = $mediaUrl; // Placeholder
            }

            // Create story
            $story = Story::create([
                'user_id' => $currentUser->id,
                'media_url' => $mediaUrl,
                'thumbnail_url' => $thumbnailUrl,
                'media_type' => $mediaType,
                'duration' => $request->input('duration', $mediaType === 'video' ? 15 : 5),
                'caption' => $request->input('caption'),
                'stickers' => $request->input('stickers') ? json_decode($request->input('stickers'), true) : null,
                'text_elements' => $request->input('text_elements') ? json_decode($request->input('text_elements'), true) : null,
                'filter' => $request->input('filter'),
                'allow_resharing' => $request->input('allow_resharing', true),
                'expires_at' => Carbon::now()->addHours(24),
            ]);

            // Load user relationship
            $story->load('user:id,name,avatar_url');

            return response()->json([
                'success' => true,
                'message' => 'Story posted successfully',
                'story' => $story
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark story as viewed
     */
    public function markAsViewed(Request $request, Story $story)
    {
        $currentUser = $request->user();

        // Prevent viewing own stories (optional)
        if ($story->user_id === $currentUser->id) {
            return response()->json([
                'success' => true,
                'message' => 'Cannot view own story'
            ]);
        }

        // Check if already viewed
        if ($story->hasViewedBy($currentUser->id)) {
            return response()->json([
                'success' => true,
                'message' => 'Story already viewed'
            ]);
        }

        try {
            // Create view record
            StoryView::create([
                'story_id' => $story->id,
                'viewer_id' => $currentUser->id,
                'viewed_at' => Carbon::now(),
            ]);

            // Increment view count
            $story->incrementViewCount();

            return response()->json([
                'success' => true,
                'message' => 'Story marked as viewed',
                'view_count' => $story->view_count
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark story as viewed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get story viewers (for story owner only) - REAL-TIME & DETAILED
     */
    public function viewers(Request $request, Story $story)
    {
        $currentUser = $request->user();

        // Only story owner can see viewers
        if ($story->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $viewers = StoryView::where('story_id', $story->id)
            ->with(['viewer:id,name,avatar_url,badge_status,badge_is_culinary_creator,show_badge,account_type'])
            ->orderBy('viewed_at', 'desc')
            ->get()
            ->map(function ($view) {
                return [
                    'id' => $view->id,
                    'viewed_at' => $view->viewed_at,
                    'viewer' => [
                        'id' => $view->viewer->id,
                        'name' => $view->viewer->name,
                        'avatar_url' => $view->viewer->avatar_url,
                        'badge_status' => $view->viewer->badge_status,
                        'badge_is_culinary_creator' => $view->viewer->badge_is_culinary_creator,
                        'show_badge' => $view->viewer->show_badge,
                        'account_type' => $view->viewer->account_type,
                        // Show badge if approved and user wants to show it
                        'has_badge' => $view->viewer->badge_status === 'approved' && $view->viewer->show_badge,
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'viewers' => $viewers,
            'total_views' => $viewers->count(),
            'latest_view_at' => $viewers->first()?->viewed_at ?? null,
        ]);
    }

    /**
     * Archive a story
     */
    public function archive(Request $request, Story $story)
    {
        $currentUser = $request->user();

        if ($story->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $story->update(['is_archived' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Story archived successfully'
        ]);
    }

    /**
     * Unarchive a story
     */
    public function unarchive(Request $request, Story $story)
    {
        $currentUser = $request->user();

        if ($story->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if story is still within 24 hours
        if ($story->is_expired) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot unarchive expired story'
            ], 400);
        }

        $story->update(['is_archived' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Story unarchived successfully'
        ]);
    }

    /**
     * Delete a story
     */
    public function destroy(Request $request, Story $story)
    {
        $currentUser = $request->user();

        if ($story->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            // Delete media from local public storage
            $path = parse_url($story->media_url, PHP_URL_PATH);
            // Extract path after /storage/
            if ($path && strpos($path, '/storage/') !== false) {
                $relativePath = str_replace('/storage/', '', $path);
                if (Storage::disk('public')->exists($relativePath)) {
                    Storage::disk('public')->delete($relativePath);
                }
            }

            // Delete thumbnail if exists
            if ($story->thumbnail_url) {
                $thumbnailPath = parse_url($story->thumbnail_url, PHP_URL_PATH);
                // Extract path after /storage/
                if ($thumbnailPath && strpos($thumbnailPath, '/storage/') !== false) {
                    $relativeThumbnailPath = str_replace('/storage/', '', $thumbnailPath);
                    if (Storage::disk('public')->exists($relativeThumbnailPath)) {
                        Storage::disk('public')->delete($relativeThumbnailPath);
                    }
                }
            }

            // Delete story (cascade will delete views)
            $story->delete();

            return response()->json([
                'success' => true,
                'message' => 'Story deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get archived stories
     */
    public function archived(Request $request)
    {
        $currentUser = $request->user();

        $archivedStories = Story::where('user_id', $currentUser->id)
            ->where('is_archived', true)
            ->where('expires_at', '>', Carbon::now())
            ->withCount('views')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'stories' => $archivedStories
        ]);
    }

    /**
     * Reply to a story (stores as message)
     */
    public function reply(Request $request, Story $story)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $currentUser = $request->user();

            // Don't allow replying to own story
            if ($story->user_id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reply to your own story'
                ], 400);
            }

            // Store reply in database
            $reply = StoryReply::create([
                'story_id' => $story->id,
                'user_id' => $currentUser->id,
                'message' => $request->message,
                'is_read' => false,
            ]);

            // TODO: Send push notification to story owner
            // TODO: Create conversation/DM thread if needed

            return response()->json([
                'success' => true,
                'message' => 'Reply sent successfully',
                'reply' => $reply
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reply',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * React to a story with emoji
     */
    public function react(Request $request, Story $story)
    {
        $validator = Validator::make($request->all(), [
            'emoji' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $currentUser = $request->user();

            // Don't allow reacting to own story
            if ($story->user_id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot react to your own story'
                ], 400);
            }

            // Store or update reaction (upsert prevents duplicate reactions)
            $reaction = StoryReaction::updateOrCreate(
                [
                    'story_id' => $story->id,
                    'user_id' => $currentUser->id,
                    'emoji' => $request->emoji,
                ],
                [
                    'created_at' => now(),
                ]
            );

            // Get reaction counts for this story
            $reactionCounts = StoryReaction::where('story_id', $story->id)
                ->selectRaw('emoji, COUNT(*) as count')
                ->groupBy('emoji')
                ->get()
                ->pluck('count', 'emoji');

            return response()->json([
                'success' => true,
                'message' => 'Reaction added successfully',
                'reaction' => $reaction,
                'reaction_counts' => $reactionCounts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add reaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Share a story (repost to user's own story)
     */
    public function share(Request $request, Story $story)
    {
        $currentUser = $request->user();

        // Check if story allows resharing
        if (!$story->allow_resharing) {
            return response()->json([
                'success' => false,
                'message' => 'This story cannot be shared'
            ], 403);
        }

        // Check if story is still active
        if ($story->is_expired) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot share an expired story'
            ], 400);
        }

        try {
            // Create a new story that references the original
            $sharedStory = Story::create([
                'user_id' => $currentUser->id,
                'media_url' => $story->media_url,
                'thumbnail_url' => $story->thumbnail_url,
                'media_type' => $story->media_type,
                'duration' => $story->duration,
                'caption' => $request->input('caption'), // Allow custom caption when sharing
                'stickers' => $story->stickers,
                'text_elements' => $story->text_elements,
                'filter' => $story->filter,
                'allow_resharing' => true,
                'expires_at' => Carbon::now()->addHours(24),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Story shared successfully',
                'story' => $sharedStory
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to share story',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report a story
     */
    public function report(Request $request, Story $story)
    {
        $currentUser = $request->user();

        // Prevent reporting own story
        if ($story->user_id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat melaporkan story sendiri'
            ], 400);
        }

        // Validate report reason
        $validated = $request->validate([
            'reason' => 'required|string|in:spam,inappropriate,harassment,false_information,other',
            'details' => 'nullable|string|max:500',
        ], [
            'reason.required' => 'Alasan laporan harus dipilih',
            'reason.in' => 'Alasan laporan tidak valid',
            'details.max' => 'Detail laporan maksimal 500 karakter',
        ]);

        // Check if user has already reported this story
        $existingReport = \App\Models\Report::where('user_id', $currentUser->id)
            ->where('story_id', $story->id)
            ->first();

        if ($existingReport) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melaporkan story ini sebelumnya'
            ], 400);
        }

        // Create the report
        try {
            \App\Models\Report::create([
                'user_id' => $currentUser->id,
                'story_id' => $story->id,
                'reportable_type' => 'story',
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Story berhasil dilaporkan. Tim kami akan meninjau laporan Anda.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melaporkan story',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
