<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Video;
use App\Models\Follow;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test show returns user profile with stats
     */
    public function test_show_returns_user_profile_with_stats()
    {
        Sanctum::actingAs($this->user);

        $targetUser = User::factory()->create();
        Video::factory()->count(5)->create([
            'user_id' => $targetUser->id,
            'status' => 'approved'
        ]);

        $response = $this->getJson("/api/users/{$targetUser->id}/profile");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'followers_count', 'following_count', 'is_following'],
                'stats' => ['videos', 'likes', 'views'],
                'videos',
            ]);
    }

    /**
     * Test show calculates follower status correctly
     */
    public function test_show_calculates_follower_status()
    {
        Sanctum::actingAs($this->user);

        $targetUser = User::factory()->create();
        Follow::create([
            'follower_id' => $this->user->id,
            'following_id' => $targetUser->id,
        ]);

        $response = $this->getJson("/api/users/{$targetUser->id}/profile");

        $response->assertStatus(200);
        $this->assertTrue($response->json('user.is_following'));
    }

    /**
     * Test videos returns only approved videos
     */
    public function test_videos_returns_only_approved_videos()
    {
        $targetUser = User::factory()->create();
        Video::factory()->count(3)->create([
            'user_id' => $targetUser->id,
            'status' => 'approved'
        ]);
        Video::factory()->count(2)->create([
            'user_id' => $targetUser->id,
            'status' => 'pending'
        ]);

        $response = $this->getJson("/api/users/{$targetUser->id}/videos");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test update profile requires authentication
     */
    public function test_update_profile_requires_authentication()
    {
        $response = $this->putJson('/api/user/profile', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test update profile updates user data
     */
    public function test_update_profile_updates_user_data()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/user/profile', [
            'name' => 'Updated Name',
            'bio' => 'Updated bio',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
            'bio' => 'Updated bio',
        ]);
    }

    /**
     * Test update profile validates email uniqueness
     */
    public function test_update_profile_validates_email_uniqueness()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create(['email' => 'other@example.com']);

        $response = $this->putJson('/api/user/profile', [
            'email' => 'other@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test update profile allows own email
     */
    public function test_update_profile_allows_own_email()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/user/profile', [
            'email' => $this->user->email,
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test update profile validates bio max length
     */
    public function test_update_profile_validates_bio_max_length()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/user/profile', [
            'bio' => str_repeat('a', 151), // Over 150 chars
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bio']);
    }

    /**
     * Test change password requires current password
     */
    public function test_change_password_requires_current_password()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/user/change-password', [
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /**
     * Test change password validates current password
     */
    public function test_change_password_validates_current_password()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/user/change-password', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test change password requires confirmation
     */
    public function test_change_password_requires_confirmation()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/user/change-password', [
            'current_password' => 'password',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'different123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    /**
     * Test change password updates password
     */
    public function test_change_password_updates_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user/change-password', [
            'current_password' => 'oldpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    /**
     * Test upload avatar requires image file
     */
    public function test_upload_avatar_requires_image_file()
    {
        Sanctum::actingAs($this->user);
        Storage::fake('s3');

        $response = $this->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    /**
     * Test upload avatar validates file size
     */
    public function test_upload_avatar_validates_file_size()
    {
        Sanctum::actingAs($this->user);
        Storage::fake('s3');

        $response = $this->postJson('/api/user/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg')->size(3000), // > 2MB
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    /**
     * Test delete account requires password
     */
    public function test_delete_account_requires_password()
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson('/api/user/account', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test delete account validates password
     */
    public function test_delete_account_validates_password()
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson('/api/user/account', [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test delete account removes user and related data
     */
    public function test_delete_account_removes_user_and_related_data()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        Sanctum::actingAs($user);

        $video = Video::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson('/api/user/account', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    /**
     * Test friends returns following users
     */
    public function test_friends_returns_following_users()
    {
        Sanctum::actingAs($this->user);

        $friend1 = User::factory()->create();
        $friend2 = User::factory()->create();

        Follow::create(['follower_id' => $this->user->id, 'following_id' => $friend1->id]);
        Follow::create(['follower_id' => $this->user->id, 'following_id' => $friend2->id]);

        $response = $this->getJson('/api/friends');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /**
     * Test notifications returns user notifications
     */
    public function test_notifications_returns_user_notifications()
    {
        Sanctum::actingAs($this->user);

        $fromUser = User::factory()->create();
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'from_user_id' => $fromUser->id,
        ]);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test mark notification as read updates notification
     */
    public function test_mark_notification_as_read_updates_notification()
    {
        Sanctum::actingAs($this->user);

        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        $response = $this->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    /**
     * Test mark notification as read prevents unauthorized access
     */
    public function test_mark_notification_prevents_unauthorized_access()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(404);
    }

    /**
     * Test mark all notifications as read updates all unread
     */
    public function test_mark_all_notifications_as_read()
    {
        Sanctum::actingAs($this->user);

        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'is_read' => false,
        ]);

        $response = $this->postJson('/api/notifications/read-all');

        $response->assertStatus(200);
        $this->assertEquals(0, Notification::where('user_id', $this->user->id)
            ->where('is_read', false)
            ->count());
    }

    /**
     * Test recommended accounts returns users with videos
     */
    public function test_recommended_accounts_returns_users_with_videos()
    {
        Sanctum::actingAs($this->user);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Video::factory()->count(3)->create(['user_id' => $user1->id]);
        Video::factory()->count(2)->create(['user_id' => $user2->id]);
        // user3 has no videos

        $response = $this->getJson('/api/users/recommended');

        $response->assertStatus(200);
        $accounts = $response->json('data');

        $this->assertGreaterThanOrEqual(2, count($accounts));
        $accountIds = collect($accounts)->pluck('id')->toArray();
        $this->assertContains($user1->id, $accountIds);
        $this->assertContains($user2->id, $accountIds);
        $this->assertNotContains($user3->id, $accountIds);
    }

    /**
     * Test recommended accounts excludes current user
     */
    public function test_recommended_accounts_excludes_current_user()
    {
        Sanctum::actingAs($this->user);

        Video::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/users/recommended');

        $response->assertStatus(200);
        $accountIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($this->user->id, $accountIds);
    }
}
