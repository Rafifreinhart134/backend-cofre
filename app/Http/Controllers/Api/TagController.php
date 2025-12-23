<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoTag;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Tag users in a video (during upload or after)
     */
    public function tagUsersInVideo(Request $request, $videoId)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $currentUser = $request->user();
        $video = Video::findOrFail($videoId);

        // Check if current user owns the video
        if ($video->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only tag users in your own videos',
            ], 403);
        }

        $taggedUsers = [];

        foreach ($request->user_ids as $userId) {
            // Don't tag yourself
            if ($userId == $currentUser->id) {
                continue;
            }

            // Check if already tagged
            $existingTag = VideoTag::where('video_id', $videoId)
                ->where('tagged_user_id', $userId)
                ->where('tag_type', 'video')
                ->first();

            if ($existingTag) {
                $taggedUsers[] = $existingTag;
                continue;
            }

            // Create new tag
            $tag = VideoTag::create([
                'video_id' => $videoId,
                'tagged_user_id' => $userId,
                'tagged_by_user_id' => $currentUser->id,
                'status' => 'pending',
                'tag_type' => 'video',
            ]);

            // Create notification for tagged user
            Notification::create([
                'user_id' => $userId,
                'from_user_id' => $currentUser->id,
                'type' => 'tag',
                'message' => 'menandai Anda di sebuah video',
                'video_id' => $videoId,
            ]);

            $taggedUsers[] = $tag;
        }

        return response()->json([
            'success' => true,
            'message' => 'Users tagged successfully',
            'data' => $taggedUsers,
        ]);
    }

    /**
     * Tag user in a comment (when mentioning with @username)
     */
    public function tagUserInComment(Request $request, $commentId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $currentUser = $request->user();
        $comment = Comment::with('video')->findOrFail($commentId);
        $userId = $request->user_id;

        // Don't tag yourself
        if ($userId == $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot tag yourself',
            ], 400);
        }

        // Check if already tagged in this comment
        $existingTag = VideoTag::where('comment_id', $commentId)
            ->where('tagged_user_id', $userId)
            ->first();

        if ($existingTag) {
            return response()->json([
                'success' => true,
                'message' => 'User already tagged',
                'data' => $existingTag,
            ]);
        }

        // Create tag
        $tag = VideoTag::create([
            'video_id' => $comment->video_id,
            'comment_id' => $commentId,
            'tagged_user_id' => $userId,
            'tagged_by_user_id' => $currentUser->id,
            'status' => 'pending',
            'tag_type' => 'comment',
        ]);

        // Create notification
        Notification::create([
            'user_id' => $userId,
            'from_user_id' => $currentUser->id,
            'type' => 'mention',
            'message' => 'menyebut Anda di komentar',
            'video_id' => $comment->video_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User tagged in comment',
            'data' => $tag,
        ]);
    }

    /**
     * Get pending tags for current user (for approval)
     */
    public function getPendingTags(Request $request)
    {
        $user = $request->user();

        $pendingTags = VideoTag::where('tagged_user_id', $user->id)
            ->where('status', 'pending')
            ->with(['video', 'taggedByUser:id,name,avatar_url'])
            ->latest()
            ->paginate(20);

        return response()->json($pendingTags);
    }

    /**
     * Approve a tag
     */
    public function approveTag(Request $request, $tagId)
    {
        $user = $request->user();
        $tag = VideoTag::findOrFail($tagId);

        // Check if user is the tagged user
        if ($tag->tagged_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $tag->approve();

        return response()->json([
            'success' => true,
            'message' => 'Tag approved',
            'data' => $tag,
        ]);
    }

    /**
     * Reject a tag
     */
    public function rejectTag(Request $request, $tagId)
    {
        $user = $request->user();
        $tag = VideoTag::findOrFail($tagId);

        // Check if user is the tagged user
        if ($tag->tagged_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $tag->reject();

        return response()->json([
            'success' => true,
            'message' => 'Tag rejected',
            'data' => $tag,
        ]);
    }

    /**
     * Remove tag (by video owner or tagged user)
     */
    public function removeTag(Request $request, $tagId)
    {
        $user = $request->user();
        $tag = VideoTag::with('video')->findOrFail($tagId);

        // Check if user is video owner or tagged user
        if ($tag->video->user_id !== $user->id && $tag->tagged_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag removed',
        ]);
    }

    /**
     * Get videos where user is tagged (for profile)
     */
    public function getTaggedVideos(Request $request, $userId = null)
    {
        $targetUserId = $userId ?? $request->user()->id;
        $currentUser = $request->user();

        $videos = Video::whereHas('tags', function ($query) use ($targetUserId) {
                $query->where('tagged_user_id', $targetUserId)
                    ->where('status', 'approved');
            })
            ->with('user:id,name,avatar_url')
            ->withCount(['likes', 'comments', 'views'])
            ->latest()
            ->paginate(20);

        // Add is_liked, is_bookmarked for each video
        if ($currentUser) {
            $videos->getCollection()->transform(function ($video) use ($currentUser) {
                $video->is_liked = $video->isLikedBy($currentUser->id);
                $video->is_bookmarked = $video->isBookmarkedBy($currentUser->id);
                $video->is_following = $currentUser->following()->where('following_id', $video->user_id)->exists();
                return $video;
            });
        }

        return response()->json($videos);
    }

    /**
     * Search users for tagging (autocomplete)
     */
    public function searchUsersForTagging(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1',
        ]);

        $query = $request->query;
        $currentUser = $request->user();

        // Search users by name or username
        $users = User::where('id', '!=', $currentUser->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->select('id', 'name', 'email', 'avatar_url')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }
}
