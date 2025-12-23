<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Video;
use App\Models\Like;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class LikeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $video;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->video = Video::factory()->create();
    }

    /**
     * Test toggle like requires authentication
     */
    public function test_toggle_like_requires_authentication()
    {
        $response = $this->postJson("/api/videos/{$this->video->id}/like");

        $response->assertStatus(401);
    }

    /**
     * Test toggle like creates like when not liked
     */
    public function test_toggle_creates_like_when_not_liked()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'liked' => true,
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $this->user->id,
            'video_id' => $this->video->id,
        ]);
    }

    /**
     * Test toggle like removes like when already liked
     */
    public function test_toggle_removes_like_when_already_liked()
    {
        Sanctum::actingAs($this->user);

        Like::create([
            'user_id' => $this->user->id,
            'video_id' => $this->video->id,
        ]);

        $response = $this->postJson("/api/videos/{$this->video->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'liked' => false,
            ]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $this->user->id,
            'video_id' => $this->video->id,
        ]);
    }

    /**
     * Test toggle like returns correct likes count
     */
    public function test_toggle_returns_correct_likes_count()
    {
        Sanctum::actingAs($this->user);

        // Create some existing likes
        Like::factory()->count(3)->create(['video_id' => $this->video->id]);

        $response = $this->postJson("/api/videos/{$this->video->id}/like");

        $response->assertStatus(200);
        $this->assertEquals(4, $response->json('likes_count'));
    }

    /**
     * Test like creates notification for video owner
     */
    public function test_like_creates_notification_for_video_owner()
    {
        Sanctum::actingAs($this->user);

        $videoOwner = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $videoOwner->id]);

        $response = $this->postJson("/api/videos/{$video->id}/like");

        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $videoOwner->id,
            'from_user_id' => $this->user->id,
            'type' => 'like',
            'video_id' => $video->id,
        ]);
    }

    /**
     * Test like does not create notification for own video
     */
    public function test_like_does_not_create_notification_for_own_video()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/videos/{$video->id}/like");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'type' => 'like',
            'video_id' => $video->id,
        ]);
    }

    /**
     * Test unlike deletes notification
     */
    public function test_unlike_deletes_notification()
    {
        Sanctum::actingAs($this->user);

        $videoOwner = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $videoOwner->id]);

        Like::create([
            'user_id' => $this->user->id,
            'video_id' => $video->id,
        ]);

        Notification::create([
            'user_id' => $videoOwner->id,
            'from_user_id' => $this->user->id,
            'type' => 'like',
            'video_id' => $video->id,
            'message' => 'liked your video',
        ]);

        $response = $this->postJson("/api/videos/{$video->id}/like");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $videoOwner->id,
            'from_user_id' => $this->user->id,
            'type' => 'like',
            'video_id' => $video->id,
        ]);
    }

    /**
     * Test toggle like handles non-existent video
     */
    public function test_toggle_like_handles_nonexistent_video()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/99999/like");

        $response->assertStatus(404);
    }

    /**
     * Test multiple users can like same video
     */
    public function test_multiple_users_can_like_same_video()
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Sanctum::actingAs($this->user);
        $this->postJson("/api/videos/{$this->video->id}/like");

        Sanctum::actingAs($user2);
        $this->postJson("/api/videos/{$this->video->id}/like");

        Sanctum::actingAs($user3);
        $response = $this->postJson("/api/videos/{$this->video->id}/like");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('likes_count'));
    }
}
