<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceTokenController extends Controller
{
    /**
     * Register a new device token for push notifications
     */
    public function register(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'device_type' => 'required|in:ios,android,web',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        // Check if token already exists for this user
        $existingToken = DeviceToken::where('user_id', $user->id)
            ->where('device_token', $request->device_token)
            ->first();

        if ($existingToken) {
            // Reactivate if deactivated
            if (!$existingToken->is_active) {
                $existingToken->is_active = true;
                $existingToken->last_used_at = now();
                $existingToken->save();
            }

            return response()->json([
                'message' => 'Device token already registered',
                'device_token' => $existingToken,
            ]);
        }

        // Create new device token
        $deviceToken = DeviceToken::create([
            'user_id' => $user->id,
            'device_token' => $request->device_token,
            'device_type' => $request->device_type,
            'device_name' => $request->device_name,
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        return response()->json([
            'message' => 'Device token registered successfully',
            'device_token' => $deviceToken,
        ], 201);
    }

    /**
     * Remove a device token (when user logs out or uninstalls app)
     */
    public function remove(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        $user = Auth::user();

        $deviceToken = DeviceToken::where('user_id', $user->id)
            ->where('device_token', $request->device_token)
            ->first();

        if (!$deviceToken) {
            return response()->json([
                'message' => 'Device token not found',
            ], 404);
        }

        $deviceToken->delete();

        return response()->json([
            'message' => 'Device token removed successfully',
        ]);
    }

    /**
     * Get all device tokens for current user
     */
    public function index()
    {
        $user = Auth::user();

        $deviceTokens = $user->deviceTokens()->get();

        return response()->json([
            'device_tokens' => $deviceTokens,
        ]);
    }

    /**
     * Deactivate a device token
     */
    public function deactivate(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        $user = Auth::user();

        $deviceToken = DeviceToken::where('user_id', $user->id)
            ->where('device_token', $request->device_token)
            ->first();

        if (!$deviceToken) {
            return response()->json([
                'message' => 'Device token not found',
            ], 404);
        }

        $deviceToken->deactivate();

        return response()->json([
            'message' => 'Device token deactivated successfully',
        ]);
    }
}
