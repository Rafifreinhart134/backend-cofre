<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\Video;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    /**
     * Get all playlists for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $playlists = Playlist::where('user_id', $user->id)
            ->withCount('videos')
            ->with(['videos' => function ($query) {
                $query->limit(4); // Get first 4 videos for thumbnail preview
            }])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $playlists,
        ]);
    }

    /**
     * Create a new playlist
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_private' => 'boolean',
        ]);

        $playlist = Playlist::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'description' => $request->description,
            'is_private' => $request->is_private ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Playlist berhasil dibuat',
            'data' => $playlist,
        ], 201);
    }

    /**
     * Get a specific playlist with its videos
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $playlist = Playlist::with(['videos.user'])
            ->withCount('videos')
            ->findOrFail($id);

        // Check if user has access to this playlist
        if ($playlist->is_private && $playlist->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this playlist',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $playlist,
        ]);
    }

    /**
     * Update a playlist
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $playlist = Playlist::findOrFail($id);

        // Check ownership
        if ($playlist->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:500',
            'is_private' => 'boolean',
        ]);

        $playlist->update($request->only(['name', 'description', 'is_private']));

        return response()->json([
            'success' => true,
            'message' => 'Playlist berhasil diupdate',
            'data' => $playlist,
        ]);
    }

    /**
     * Delete a playlist
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $playlist = Playlist::findOrFail($id);

        // Check ownership
        if ($playlist->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $playlist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Playlist berhasil dihapus',
        ]);
    }

    /**
     * Add a video to a playlist
     */
    public function addVideo(Request $request, $playlistId)
    {
        $user = $request->user();

        $playlist = Playlist::findOrFail($playlistId);

        // Check ownership
        if ($playlist->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'video_id' => 'required|exists:videos,id',
        ]);

        $video = Video::findOrFail($request->video_id);

        // Check if video is already in playlist (using wherePivot for many-to-many)
        if ($playlist->videos()->where('videos.id', $video->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Video sudah ada di playlist',
            ], 400);
        }

        // Get the next position
        $maxPosition = $playlist->videos()->max('position') ?? -1;

        $playlist->videos()->attach($video->id, [
            'position' => $maxPosition + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Video berhasil ditambahkan ke playlist',
        ]);
    }

    /**
     * Remove a video from a playlist
     */
    public function removeVideo(Request $request, $playlistId, $videoId)
    {
        $user = $request->user();

        $playlist = Playlist::findOrFail($playlistId);

        // Check ownership
        if ($playlist->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $playlist->videos()->detach($videoId);

        return response()->json([
            'success' => true,
            'message' => 'Video berhasil dihapus dari playlist',
        ]);
    }

    /**
     * Get a user's public playlists
     */
    public function getUserPlaylists(Request $request, $userId)
    {
        $playlists = Playlist::where('user_id', $userId)
            ->where('is_private', false) // Only public playlists
            ->withCount('videos')
            ->with(['videos' => function ($query) {
                $query->limit(4); // Get first 4 videos for thumbnail preview
            }])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $playlists,
        ]);
    }
}
