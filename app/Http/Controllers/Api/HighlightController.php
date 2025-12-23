<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Highlight;
use App\Models\HighlightItem;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class HighlightController extends Controller
{
    /**
     * Get user's highlights
     */
    public function index(Request $request, $userId = null)
    {
        $targetUserId = $userId ?? $request->user()->id;

        $highlights = Highlight::where('user_id', $targetUserId)
            ->orderBy('order', 'asc')
            ->withCount('items')
            ->with(['stories' => function ($query) {
                $query->select('stories.id', 'stories.media_url', 'stories.thumbnail_url', 'stories.media_type')
                      ->limit(1);
            }])
            ->get();

        return response()->json([
            'success' => true,
            'highlights' => $highlights
        ]);
    }

    /**
     * Get specific highlight with all stories
     */
    public function show(Request $request, Highlight $highlight)
    {
        $highlight->load(['stories' => function ($query) {
            $query->with('user:id,name,avatar_url')
                  ->orderByPivot('order', 'asc');
        }]);

        return response()->json([
            'success' => true,
            'highlight' => $highlight
        ]);
    }

    /**
     * Create a new highlight
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'cover_image' => 'nullable|image|max:10240', // Max 10MB
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

            $coverImageUrl = null;
            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = 'highlights/' . $currentUser->id . '/' . $filename;

                // Use local public storage like videos and stories
                Storage::disk('public')->put($path, file_get_contents($file));
                $coverImageUrl = url('storage/' . $path);
            }

            // Get the next order number
            $maxOrder = Highlight::where('user_id', $currentUser->id)->max('order') ?? -1;

            $highlight = Highlight::create([
                'user_id' => $currentUser->id,
                'title' => $request->input('title'),
                'cover_image_url' => $coverImageUrl,
                'order' => $maxOrder + 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Highlight created successfully',
                'highlight' => $highlight
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create highlight',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a highlight
     */
    public function update(Request $request, Highlight $highlight)
    {
        $currentUser = $request->user();

        if ($highlight->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:100',
            'cover_image' => 'nullable|image|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->has('title')) {
                $highlight->title = $request->input('title');
            }

            if ($request->hasFile('cover_image')) {
                // Delete old cover image if exists
                if ($highlight->cover_image_url) {
                    $relativePath = str_replace(url('storage/'), '', $highlight->cover_image_url);
                    if (Storage::disk('public')->exists($relativePath)) {
                        Storage::disk('public')->delete($relativePath);
                    }
                }

                $file = $request->file('cover_image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = 'highlights/' . $currentUser->id . '/' . $filename;

                // Use local public storage like videos and stories
                Storage::disk('public')->put($path, file_get_contents($file));
                $highlight->cover_image_url = url('storage/' . $path);
            }

            $highlight->save();

            return response()->json([
                'success' => true,
                'message' => 'Highlight updated successfully',
                'highlight' => $highlight
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update highlight',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a highlight
     */
    public function destroy(Request $request, Highlight $highlight)
    {
        $currentUser = $request->user();

        if ($highlight->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            // Delete cover image if exists
            if ($highlight->cover_image_url) {
                $path = parse_url($highlight->cover_image_url, PHP_URL_PATH);
                if ($path && Storage::disk('s3')->exists($path)) {
                    Storage::disk('s3')->delete($path);
                }
            }

            $highlight->delete();

            return response()->json([
                'success' => true,
                'message' => 'Highlight deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete highlight',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add story to highlight
     */
    public function addStory(Request $request, Highlight $highlight)
    {
        $currentUser = $request->user();

        if ($highlight->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'story_id' => 'required|exists:stories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $story = Story::find($request->story_id);

            // Verify the story belongs to the user
            if ($story->user_id !== $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only add your own stories to highlights'
                ], 403);
            }

            // Check if story is already in this highlight
            $exists = HighlightItem::where('highlight_id', $highlight->id)
                                  ->where('story_id', $story->id)
                                  ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Story already in this highlight'
                ], 400);
            }

            // Get the next order number
            $maxOrder = HighlightItem::where('highlight_id', $highlight->id)->max('order') ?? -1;

            HighlightItem::create([
                'highlight_id' => $highlight->id,
                'story_id' => $story->id,
                'order' => $maxOrder + 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Story added to highlight successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add story to highlight',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove story from highlight
     */
    public function removeStory(Request $request, Highlight $highlight, Story $story)
    {
        $currentUser = $request->user();

        if ($highlight->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            HighlightItem::where('highlight_id', $highlight->id)
                        ->where('story_id', $story->id)
                        ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Story removed from highlight successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove story from highlight',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder highlights
     */
    public function reorder(Request $request)
    {
        $currentUser = $request->user();

        $validator = Validator::make($request->all(), [
            'highlights' => 'required|array',
            'highlights.*.id' => 'required|exists:story_highlights,id',
            'highlights.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->highlights as $item) {
                $highlight = Highlight::find($item['id']);

                if ($highlight->user_id !== $currentUser->id) {
                    continue;
                }

                $highlight->order = $item['order'];
                $highlight->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Highlights reordered successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder highlights',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
