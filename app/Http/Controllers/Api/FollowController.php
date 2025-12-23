<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    /**
     * Toggle follow on a user
     */
    public function toggle(Request $request, $userId)
    {
        $currentUser = $request->user();
        $targetUser = User::findOrFail($userId);

        // Prevent self-follow
        if ($currentUser->id === $targetUser->id) {
            return response()->json([
                'message' => 'You cannot follow yourself',
            ], 400);
        }

        $existingFollow = Follow::where('follower_id', $currentUser->id)
            ->where('following_id', $targetUser->id)
            ->first();

        if ($existingFollow) {
            // Unfollow
            $existingFollow->delete();

            // Delete the follow notification
            \App\Models\Notification::where('user_id', $targetUser->id)
                ->where('from_user_id', $currentUser->id)
                ->where('type', 'follow')
                ->delete();

            return response()->json([
                'message' => 'Unfollowed successfully',
                'following' => false,
            ]);
        } else {
            // Follow
            Follow::create([
                'follower_id' => $currentUser->id,
                'following_id' => $targetUser->id,
            ]);

            // Create notification
            \App\Models\Notification::create([
                'user_id' => $targetUser->id,
                'from_user_id' => $currentUser->id,
                'type' => 'follow',
                'message' => 'mulai mengikuti Anda',
            ]);

            return response()->json([
                'message' => 'Followed successfully',
                'following' => true,
            ]);
        }
    }
}
