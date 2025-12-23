<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Video;
use App\Models\Like;
use App\Models\Bookmark;
use App\Models\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class VideoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test video index returns paginated videos
     */
    public function test_index_returns_paginated_videos()
    {
        Video::factory()->count(25)->create();

        $response = $this->getJson('/api/videos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
                'last_page',
            ]);

        $this->assertCount(20, $response->json('data')); // Default per page is 20
    }

    /**
     * Test index calculates engagement score correctly
     */
    public function test_index_calculates_engagement_score()
    {
        $video = Video::factory()->create();

        // Create likes, comments, and views
        Like::factory()->count(5)->create(['video_id' => $video->id]);

        $response = $this->getJson('/api/videos');

        $response->assertStatus(200);
        $videoData = collect($response->json('data'))->firstWhere('id', $video->id);

        $this->assertNotNull($videoData);
        $this->assertArrayHasKey('recommendation_score', $videoData);
    }

    /**
     * Test authenticated user sees like and bookmark status
     */
    public function test_authenticated_user_sees_interaction_status()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create();
        Like::create(['user_id' => $this->user->id, 'video_id' => $video->id]);

        $response = $this->getJson('/api/videos');

        $response->assertStatus(200);
        $videoData = collect($response->json('data'))->firstWhere('id', $video->id);

        $this->assertTrue($videoData['is_liked']);
        $this->assertFalse($videoData['is_bookmarked']);
    }

    /**
     * Test guest user sees all interaction flags as false
     */
    public function test_guest_sees_all_flags_false()
    {
        $video = Video::factory()->create();

        $response = $this->getJson('/api/videos');

        $response->assertStatus(200);
        $videoData = collect($response->json('data'))->firstWhere('id', $video->id);

        $this->assertFalse($videoData['is_liked']);
        $this->assertFalse($videoData['is_bookmarked']);
        $this->assertFalse($videoData['is_following']);
    }

    /**
     * Test video upload requires authentication
     */
    public function test_upload_requires_authentication()
    {
        Storage::fake('public');

        $response = $this->postJson('/api/videos/upload', [
            'video' => UploadedFile::fake()->create('video.mp4', 1000),
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg'),
            'menu_data' => json_encode(['dish' => 'Test Dish', 'calories' => 500]),
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test video upload validation fails without required fields
     */
    public function test_upload_validation_fails_without_required_fields()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/videos/upload', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video', 'thumbnail', 'menu_data']);
    }

    /**
     * Test video upload validation fails with invalid file type
     */
    public function test_upload_validation_fails_with_invalid_file_type()
    {
        Sanctum::actingAs($this->user);
        Storage::fake('public');

        $response = $this->postJson('/api/videos/upload', [
            'video' => UploadedFile::fake()->create('video.txt', 1000),
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg'),
            'menu_data' => json_encode(['dish' => 'Test Dish']),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video']);
    }

    /**
     * Test video upload validation fails with oversized file
     */
    public function test_upload_validation_fails_with_oversized_file()
    {
        Sanctum::actingAs($this->user);
        Storage::fake('public');

        $response = $this->postJson('/api/videos/upload', [
            'video' => UploadedFile::fake()->create('video.mp4', 110000), // > 100MB
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg'),
            'menu_data' => json_encode(['dish' => 'Test Dish']),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video']);
    }

    /**
     * Test successful video upload creates database record
     */
    public function test_successful_upload_creates_database_record()
    {
        Sanctum::actingAs($this->user);
        Storage::fake('public');

        $response = $this->postJson('/api/videos/upload', [
            'video' => UploadedFile::fake()->create('video.mp4', 1000),
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg'),
            'menu_data' => json_encode(['dish' => 'Nasi Goreng', 'calories' => 500]),
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'video' => ['id', 's3_url', 'thumbnail_url', 'menu_data'],
            ]);

        $this->assertDatabaseHas('videos', [
            'user_id' => $this->user->id,
            'status' => 'approved',
        ]);
    }

    /**
     * Test my videos returns only user's videos
     */
    public function test_my_videos_returns_only_users_videos()
    {
        Sanctum::actingAs($this->user);

        Video::factory()->count(3)->create(['user_id' => $this->user->id]);
        Video::factory()->count(5)->create(); // Other users' videos

        $response = $this->getJson('/api/videos/my-videos');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test following feed returns videos from followed users only
     */
    public function test_following_returns_only_followed_users_videos()
    {
        Sanctum::actingAs($this->user);

        $followedUser = User::factory()->create();
        Follow::create([
            'follower_id' => $this->user->id,
            'following_id' => $followedUser->id,
        ]);

        Video::factory()->count(3)->create(['user_id' => $followedUser->id]);
        Video::factory()->count(5)->create(); // Other users' videos

        $response = $this->getJson('/api/videos/following');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test following feed returns empty when not following anyone
     */
    public function test_following_returns_empty_when_not_following_anyone()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/videos/following');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Test record view creates view record
     */
    public function test_record_view_creates_view_record()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create();

        $response = $this->postJson("/api/videos/{$video->id}/view");

        $response->assertStatus(200);
        $this->assertDatabaseHas('views', [
            'video_id' => $video->id,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Test search finds videos by menu data
     */
    public function test_search_finds_videos_by_menu_data()
    {
        Video::factory()->create([
            'menu_data' => json_encode(['dish' => 'Nasi Goreng', 'calories' => 500])
        ]);
        Video::factory()->create([
            'menu_data' => json_encode(['dish' => 'Pizza', 'calories' => 800])
        ]);

        $response = $this->getJson('/api/videos/search?q=Nasi');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    /**
     * Test delete video requires ownership
     */
    public function test_delete_video_requires_ownership()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->deleteJson("/api/videos/{$video->id}");

        $response->assertStatus(403);
    }

    /**
     * Test owner can delete their video
     */
    public function test_owner_can_delete_their_video()
    {
        Sanctum::actingAs($this->user);
        Storage::fake('s3');

        $video = Video::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/videos/{$video->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    /**
     * Test repost prevents duplicate reposts
     */
    public function test_repost_prevents_duplicates()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create();

        // First repost should succeed
        $response = $this->postJson("/api/videos/{$video->id}/repost");
        $response->assertStatus(200);

        // Second repost should fail
        $response = $this->postJson("/api/videos/{$video->id}/repost");
        $response->assertStatus(400);
    }

    /**
     * Test not interested sets preference
     */
    public function test_not_interested_sets_preference()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create();

        $response = $this->postJson("/api/videos/{$video->id}/not-interested");

        $response->assertStatus(200);
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'video_id' => $video->id,
            'not_interested' => true,
        ]);
    }

    /**
     * Test report video requires reason
     */
    public function test_report_requires_reason()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create();

        $response = $this->postJson("/api/videos/{$video->id}/report", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    /**
     * Test report video creates report
     */
    public function test_report_creates_report()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create();

        $response = $this->postJson("/api/videos/{$video->id}/report", [
            'reason' => 'Inappropriate content',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reports', [
            'user_id' => $this->user->id,
            'video_id' => $video->id,
            'reason' => 'Inappropriate content',
        ]);
    }

    /**
     * Test report prevents duplicate reports
     */
    public function test_report_prevents_duplicates()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create();

        $this->postJson("/api/videos/{$video->id}/report", [
            'reason' => 'Spam',
        ]);

        $response = $this->postJson("/api/videos/{$video->id}/report", [
            'reason' => 'Spam',
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test share to friend requires valid friend_id
     */
    public function test_share_requires_valid_friend_id()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create();

        $response = $this->postJson("/api/videos/{$video->id}/share", [
            'friend_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['friend_id']);
    }

    /**
     * Test share to friend creates share record
     */
    public function test_share_creates_share_record()
    {
        Sanctum::actingAs($this->user);

        $friend = User::factory()->create();
        $video = Video::factory()->create();

        $response = $this->postJson("/api/videos/{$video->id}/share", [
            'friend_id' => $friend->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('shares', [
            'user_id' => $this->user->id,
            'video_id' => $video->id,
            'recipient_id' => $friend->id,
        ]);
    }
}
