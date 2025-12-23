<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Video;
use App\Models\Comment;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class CommentControllerTest extends TestCase
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
     * Test index returns video comments
     */
    public function test_index_returns_video_comments()
    {
        Comment::factory()->count(5)->create(['video_id' => $this->video->id]);

        $response = $this->getJson("/api/videos/{$this->video->id}/comments");

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    /**
     * Test index handles non-existent video
     */
    public function test_index_handles_nonexistent_video()
    {
        $response = $this->getJson("/api/videos/99999/comments");

        $response->assertStatus(404);
    }

    /**
     * Test store comment requires authentication
     */
    public function test_store_requires_authentication()
    {
        $response = $this->postJson("/api/videos/{$this->video->id}/comments", [
            'content' => 'Nice video!',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test store comment creates comment
     */
    public function test_store_creates_comment()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/comments", [
            'content' => 'Great content!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'comment' => ['id', 'content', 'user_id', 'video_id'],
            ]);

        $this->assertDatabaseHas('comments', [
            'user_id' => $this->user->id,
            'video_id' => $this->video->id,
            'content' => 'Great content!',
        ]);
    }

    /**
     * Test store comment requires content
     */
    public function test_store_requires_content()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/comments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /**
     * Test store comment validates content min length
     */
    public function test_store_validates_content_min_length()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/comments", [
            'content' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /**
     * Test store comment validates content max length
     */
    public function test_store_validates_content_max_length()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/comments", [
            'content' => str_repeat('a', 501), // Over 500 chars
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /**
     * Test store comment rejects HTML tags (XSS protection)
     */
    public function test_store_rejects_html_tags()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/comments", [
            'content' => 'Nice <script>alert("XSS")</script> video!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /**
     * Test store comment rejects javascript protocol
     */
    public function test_store_rejects_javascript_protocol()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/comments", [
            'content' => 'Click here javascript:alert("XSS")',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /**
     * Test store comment sanitizes content
     */
    public function test_store_sanitizes_content()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/comments", [
            'content' => '  Great video!  ',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('comments', [
            'content' => 'Great video!', // Trimmed
        ]);
    }

    /**
     * Test store comment creates notification for video owner
     */
    public function test_store_creates_notification_for_video_owner()
    {
        Sanctum::actingAs($this->user);

        $videoOwner = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $videoOwner->id]);

        $response = $this->postJson("/api/videos/{$video->id}/comments", [
            'content' => 'Nice video!',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $videoOwner->id,
            'from_user_id' => $this->user->id,
            'type' => 'comment',
            'video_id' => $video->id,
        ]);
    }

    /**
     * Test store comment does not create notification for own video
     */
    public function test_store_does_not_create_notification_for_own_video()
    {
        Sanctum::actingAs($this->user);

        $video = Video::factory()->create(['user_id' => $this->user->id]);

        $response = $this->postJson("/api/videos/{$video->id}/comments", [
            'content' => 'Self comment',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'type' => 'comment',
            'video_id' => $video->id,
        ]);
    }

    /**
     * Test destroy comment requires authentication
     */
    public function test_destroy_requires_authentication()
    {
        $comment = Comment::factory()->create();

        $response = $this->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(401);
    }

    /**
     * Test destroy comment requires ownership
     */
    public function test_destroy_requires_ownership()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(403);
    }

    /**
     * Test owner can delete their comment
     */
    public function test_owner_can_delete_their_comment()
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    /**
     * Test destroy comment handles non-existent comment
     */
    public function test_destroy_handles_nonexistent_comment()
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/comments/99999");

        $response->assertStatus(404);
    }

    /**
     * Test comments are returned with user information
     */
    public function test_comments_include_user_information()
    {
        $commenter = User::factory()->create(['name' => 'Test Commenter']);
        Comment::factory()->create([
            'video_id' => $this->video->id,
            'user_id' => $commenter->id,
        ]);

        $response = $this->getJson("/api/videos/{$this->video->id}/comments");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Test Commenter']);
    }

    /**
     * Test comments are ordered by latest first
     */
    public function test_comments_ordered_by_latest_first()
    {
        $comment1 = Comment::factory()->create([
            'video_id' => $this->video->id,
            'created_at' => now()->subHours(2),
        ]);
        $comment2 = Comment::factory()->create([
            'video_id' => $this->video->id,
            'created_at' => now()->subHour(),
        ]);
        $comment3 = Comment::factory()->create([
            'video_id' => $this->video->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/videos/{$this->video->id}/comments");

        $response->assertStatus(200);
        $comments = $response->json('data');

        $this->assertEquals($comment3->id, $comments[0]['id']);
        $this->assertEquals($comment2->id, $comments[1]['id']);
        $this->assertEquals($comment1->id, $comments[2]['id']);
    }
}
