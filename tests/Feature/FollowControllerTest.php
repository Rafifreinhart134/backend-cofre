<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Follow;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class FollowControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->targetUser = User::factory()->create();
    }

    /**
     * Test toggle follow requires authentication
     */
    public function test_toggle_requires_authentication()
    {
        $response = $this->postJson("/api/users/{$this->targetUser->id}/follow");

        $response->assertStatus(401);
    }

    /**
     * Test toggle follow creates follow when not following
     */
    public function test_toggle_creates_follow_when_not_following()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/users/{$this->targetUser->id}/follow");

        $response->assertStatus(200)
            ->assertJson([
                'following' => true,
            ]);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $this->user->id,
            'following_id' => $this->targetUser->id,
        ]);
    }

    /**
     * Test toggle follow removes follow when already following
     */
    public function test_toggle_removes_follow_when_already_following()
    {
        Sanctum::actingAs($this->user);

        Follow::create([
            'follower_id' => $this->user->id,
            'following_id' => $this->targetUser->id,
        ]);

        $response = $this->postJson("/api/users/{$this->targetUser->id}/follow");

        $response->assertStatus(200)
            ->assertJson([
                'following' => false,
            ]);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $this->user->id,
            'following_id' => $this->targetUser->id,
        ]);
    }

    /**
     * Test follow prevents self-follow
     */
    public function test_follow_prevents_self_follow()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/users/{$this->user->id}/follow");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'You cannot follow yourself']);
    }

    /**
     * Test follow creates notification
     */
    public function test_follow_creates_notification()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/users/{$this->targetUser->id}/follow");

        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->targetUser->id,
            'from_user_id' => $this->user->id,
            'type' => 'follow',
        ]);
    }

    /**
     * Test unfollow deletes notification
     */
    public function test_unfollow_deletes_notification()
    {
        Sanctum::actingAs($this->user);

        Follow::create([
            'follower_id' => $this->user->id,
            'following_id' => $this->targetUser->id,
        ]);

        Notification::create([
            'user_id' => $this->targetUser->id,
            'from_user_id' => $this->user->id,
            'type' => 'follow',
            'message' => 'started following you',
        ]);

        $response = $this->postJson("/api/users/{$this->targetUser->id}/follow");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->targetUser->id,
            'from_user_id' => $this->user->id,
            'type' => 'follow',
        ]);
    }

    /**
     * Test follow handles non-existent user
     */
    public function test_follow_handles_nonexistent_user()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/users/99999/follow");

        $response->assertStatus(404);
    }

    /**
     * Test multiple users can follow same user
     */
    public function test_multiple_users_can_follow_same_user()
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Sanctum::actingAs($this->user);
        $this->postJson("/api/users/{$this->targetUser->id}/follow");

        Sanctum::actingAs($user2);
        $this->postJson("/api/users/{$this->targetUser->id}/follow");

        Sanctum::actingAs($user3);
        $this->postJson("/api/users/{$this->targetUser->id}/follow");

        $this->assertEquals(3, Follow::where('following_id', $this->targetUser->id)->count());
    }

    /**
     * Test user can follow multiple users
     */
    public function test_user_can_follow_multiple_users()
    {
        Sanctum::actingAs($this->user);

        $target1 = User::factory()->create();
        $target2 = User::factory()->create();
        $target3 = User::factory()->create();

        $this->postJson("/api/users/{$target1->id}/follow");
        $this->postJson("/api/users/{$target2->id}/follow");
        $this->postJson("/api/users/{$target3->id}/follow");

        $this->assertEquals(3, Follow::where('follower_id', $this->user->id)->count());
    }
}
