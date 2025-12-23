<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Video;
use App\Models\Bookmark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class BookmarkControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $video;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->video = Video::factory()->create(['status' => 'approved']);
    }

    /**
     * Test index requires authentication
     */
    public function test_index_requires_authentication()
    {
        $response = $this->getJson('/api/bookmarks');

        $response->assertStatus(401);
    }

    /**
     * Test index returns user bookmarks
     */
    public function test_index_returns_user_bookmarks()
    {
        Sanctum::actingAs($this->user);

        Bookmark::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/bookmarks');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    /**
     * Test index only returns approved videos
     */
    public function test_index_only_returns_approved_videos()
    {
        Sanctum::actingAs($this->user);

        $approvedVideo = Video::factory()->create(['status' => 'approved']);
        $pendingVideo = Video::factory()->create(['status' => 'pending']);

        Bookmark::create(['user_id' => $this->user->id, 'video_id' => $approvedVideo->id]);
        Bookmark::create(['user_id' => $this->user->id, 'video_id' => $pendingVideo->id]);

        $response = $this->getJson('/api/bookmarks');

        $response->assertStatus(200);
        $bookmarks = collect($response->json('data'));

        $this->assertTrue($bookmarks->contains(function ($item) use ($approvedVideo) {
            return $item['video']['id'] === $approvedVideo->id;
        }));
    }

    /**
     * Test toggle bookmark requires authentication
     */
    public function test_toggle_requires_authentication()
    {
        $response = $this->postJson("/api/videos/{$this->video->id}/bookmark");

        $response->assertStatus(401);
    }

    /**
     * Test toggle creates bookmark when not bookmarked
     */
    public function test_toggle_creates_bookmark_when_not_bookmarked()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/{$this->video->id}/bookmark");

        $response->assertStatus(200)
            ->assertJson([
                'bookmarked' => true,
            ]);

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $this->user->id,
            'video_id' => $this->video->id,
        ]);
    }

    /**
     * Test toggle removes bookmark when already bookmarked
     */
    public function test_toggle_removes_bookmark_when_already_bookmarked()
    {
        Sanctum::actingAs($this->user);

        Bookmark::create([
            'user_id' => $this->user->id,
            'video_id' => $this->video->id,
        ]);

        $response = $this->postJson("/api/videos/{$this->video->id}/bookmark");

        $response->assertStatus(200)
            ->assertJson([
                'bookmarked' => false,
            ]);

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $this->user->id,
            'video_id' => $this->video->id,
        ]);
    }

    /**
     * Test toggle handles non-existent video
     */
    public function test_toggle_handles_nonexistent_video()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/videos/99999/bookmark");

        $response->assertStatus(404);
    }

    /**
     * Test user can bookmark multiple videos
     */
    public function test_user_can_bookmark_multiple_videos()
    {
        Sanctum::actingAs($this->user);

        $video1 = Video::factory()->create();
        $video2 = Video::factory()->create();
        $video3 = Video::factory()->create();

        $this->postJson("/api/videos/{$video1->id}/bookmark");
        $this->postJson("/api/videos/{$video2->id}/bookmark");
        $this->postJson("/api/videos/{$video3->id}/bookmark");

        $this->assertEquals(3, Bookmark::where('user_id', $this->user->id)->count());
    }

    /**
     * Test multiple users can bookmark same video
     */
    public function test_multiple_users_can_bookmark_same_video()
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Sanctum::actingAs($this->user);
        $this->postJson("/api/videos/{$this->video->id}/bookmark");

        Sanctum::actingAs($user2);
        $this->postJson("/api/videos/{$this->video->id}/bookmark");

        Sanctum::actingAs($user3);
        $this->postJson("/api/videos/{$this->video->id}/bookmark");

        $this->assertEquals(3, Bookmark::where('video_id', $this->video->id)->count());
    }
}
