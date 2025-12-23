<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Video;
use App\Models\Like;
use App\Models\Comment;
use App\Models\Bookmark;
use App\Models\Follow;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user has videos relationship
     */
    public function test_user_has_videos_relationship()
    {
        $user = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(Video::class, $user->videos->first());
        $this->assertEquals($video->id, $user->videos->first()->id);
    }

    /**
     * Test user has likes relationship
     */
    public function test_user_has_likes_relationship()
    {
        $user = User::factory()->create();
        $video = Video::factory()->create();
        $like = Like::create(['user_id' => $user->id, 'video_id' => $video->id]);

        $this->assertInstanceOf(Like::class, $user->likes->first());
        $this->assertEquals($like->id, $user->likes->first()->id);
    }

    /**
     * Test user has comments relationship
     */
    public function test_user_has_comments_relationship()
    {
        $user = User::factory()->create();
        $video = Video::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id, 'video_id' => $video->id]);

        $this->assertInstanceOf(Comment::class, $user->comments->first());
        $this->assertEquals($comment->id, $user->comments->first()->id);
    }

    /**
     * Test user has bookmarks relationship
     */
    public function test_user_has_bookmarks_relationship()
    {
        $user = User::factory()->create();
        $video = Video::factory()->create();
        $bookmark = Bookmark::create(['user_id' => $user->id, 'video_id' => $video->id]);

        $this->assertInstanceOf(Bookmark::class, $user->bookmarks->first());
        $this->assertEquals($bookmark->id, $user->bookmarks->first()->id);
    }

    /**
     * Test user has notifications relationship
     */
    public function test_user_has_notifications_relationship()
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(Notification::class, $user->notifications->first());
        $this->assertEquals($notification->id, $user->notifications->first()->id);
    }

    /**
     * Test user has following relationship
     */
    public function test_user_has_following_relationship()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        Follow::create([
            'follower_id' => $user->id,
            'following_id' => $targetUser->id,
        ]);

        $this->assertInstanceOf(User::class, $user->following->first());
        $this->assertEquals($targetUser->id, $user->following->first()->id);
    }

    /**
     * Test user has followers relationship
     */
    public function test_user_has_followers_relationship()
    {
        $user = User::factory()->create();
        $follower = User::factory()->create();

        Follow::create([
            'follower_id' => $follower->id,
            'following_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $user->followers->first());
        $this->assertEquals($follower->id, $user->followers->first()->id);
    }

    /**
     * Test isFollowing returns true when following
     */
    public function test_is_following_returns_true_when_following()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        Follow::create([
            'follower_id' => $user->id,
            'following_id' => $targetUser->id,
        ]);

        $this->assertTrue($user->isFollowing($targetUser->id));
    }

    /**
     * Test isFollowing returns false when not following
     */
    public function test_is_following_returns_false_when_not_following()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->assertFalse($user->isFollowing($targetUser->id));
    }

    /**
     * Test isFollowedBy returns true when followed
     */
    public function test_is_followed_by_returns_true_when_followed()
    {
        $user = User::factory()->create();
        $follower = User::factory()->create();

        Follow::create([
            'follower_id' => $follower->id,
            'following_id' => $user->id,
        ]);

        $this->assertTrue($user->isFollowedBy($follower->id));
    }

    /**
     * Test isFollowedBy returns false when not followed
     */
    public function test_is_followed_by_returns_false_when_not_followed()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->assertFalse($user->isFollowedBy($otherUser->id));
    }

    /**
     * Test followers count attribute
     */
    public function test_followers_count_attribute()
    {
        $user = User::factory()->create();

        $follower1 = User::factory()->create();
        $follower2 = User::factory()->create();
        $follower3 = User::factory()->create();

        Follow::create(['follower_id' => $follower1->id, 'following_id' => $user->id]);
        Follow::create(['follower_id' => $follower2->id, 'following_id' => $user->id]);
        Follow::create(['follower_id' => $follower3->id, 'following_id' => $user->id]);

        $this->assertEquals(3, $user->followers_count);
    }

    /**
     * Test following count attribute
     */
    public function test_following_count_attribute()
    {
        $user = User::factory()->create();

        $target1 = User::factory()->create();
        $target2 = User::factory()->create();

        Follow::create(['follower_id' => $user->id, 'following_id' => $target1->id]);
        Follow::create(['follower_id' => $user->id, 'following_id' => $target2->id]);

        $this->assertEquals(2, $user->following_count);
    }

    /**
     * Test password is hidden in JSON
     */
    public function test_password_is_hidden_in_json()
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
    }

    /**
     * Test remember token is hidden in JSON
     */
    public function test_remember_token_is_hidden_in_json()
    {
        $user = User::factory()->create();

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    /**
     * Test user fillable attributes
     */
    public function test_user_fillable_attributes()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'bio' => 'Test bio',
            'avatar_url' => 'https://example.com/avatar.jpg',
        ]);

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('Test bio', $user->bio);
        $this->assertEquals('https://example.com/avatar.jpg', $user->avatar_url);
    }

    /**
     * Test password is automatically hashed
     */
    public function test_password_is_automatically_hashed()
    {
        $user = User::factory()->create([
            'password' => 'plainpassword',
        ]);

        $this->assertNotEquals('plainpassword', $user->password);
        $this->assertTrue(strlen($user->password) > 20); // Hashed passwords are long
    }
}
