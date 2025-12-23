<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Video;
use App\Models\Like;
use App\Models\Comment;
use App\Models\View;
use App\Models\Bookmark;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VideoModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test video belongs to user
     */
    public function test_video_belongs_to_user()
    {
        $user = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $video->user);
        $this->assertEquals($user->id, $video->user->id);
    }

    /**
     * Test video has likes relationship
     */
    public function test_video_has_likes_relationship()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();
        $like = Like::create(['user_id' => $user->id, 'video_id' => $video->id]);

        $this->assertInstanceOf(Like::class, $video->likes->first());
        $this->assertEquals($like->id, $video->likes->first()->id);
    }

    /**
     * Test video has comments relationship
     */
    public function test_video_has_comments_relationship()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id, 'video_id' => $video->id]);

        $this->assertInstanceOf(Comment::class, $video->comments->first());
        $this->assertEquals($comment->id, $video->comments->first()->id);
    }

    /**
     * Test video has views relationship
     */
    public function test_video_has_views_relationship()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();
        $view = View::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'ip_address' => '127.0.0.1',
            'viewed_at' => now(),
        ]);

        $this->assertInstanceOf(View::class, $video->views->first());
        $this->assertEquals($view->id, $video->views->first()->id);
    }

    /**
     * Test video has bookmarks relationship
     */
    public function test_video_has_bookmarks_relationship()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();
        $bookmark = Bookmark::create(['user_id' => $user->id, 'video_id' => $video->id]);

        $this->assertInstanceOf(Bookmark::class, $video->bookmarks->first());
        $this->assertEquals($bookmark->id, $video->bookmarks->first()->id);
    }

    /**
     * Test likes count attribute
     */
    public function test_likes_count_attribute()
    {
        $video = Video::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        Like::create(['user_id' => $user1->id, 'video_id' => $video->id]);
        Like::create(['user_id' => $user2->id, 'video_id' => $video->id]);
        Like::create(['user_id' => $user3->id, 'video_id' => $video->id]);

        $this->assertEquals(3, $video->likes_count);
    }

    /**
     * Test comments count attribute
     */
    public function test_comments_count_attribute()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();

        Comment::factory()->count(5)->create([
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);

        $this->assertEquals(5, $video->comments_count);
    }

    /**
     * Test views count attribute
     */
    public function test_views_count_attribute()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            View::create([
                'user_id' => $user->id,
                'video_id' => $video->id,
                'ip_address' => '127.0.0.1',
                'viewed_at' => now(),
            ]);
        }

        $this->assertEquals(10, $video->views_count);
    }

    /**
     * Test isLikedBy returns true when liked
     */
    public function test_is_liked_by_returns_true_when_liked()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();

        Like::create(['user_id' => $user->id, 'video_id' => $video->id]);

        $this->assertTrue($video->isLikedBy($user->id));
    }

    /**
     * Test isLikedBy returns false when not liked
     */
    public function test_is_liked_by_returns_false_when_not_liked()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($video->isLikedBy($user->id));
    }

    /**
     * Test isLikedBy returns false for null user
     */
    public function test_is_liked_by_returns_false_for_null_user()
    {
        $video = Video::factory()->create();

        $this->assertFalse($video->isLikedBy(null));
    }

    /**
     * Test isBookmarkedBy returns true when bookmarked
     */
    public function test_is_bookmarked_by_returns_true_when_bookmarked()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();

        Bookmark::create(['user_id' => $user->id, 'video_id' => $video->id]);

        $this->assertTrue($video->isBookmarkedBy($user->id));
    }

    /**
     * Test isBookmarkedBy returns false when not bookmarked
     */
    public function test_is_bookmarked_by_returns_false_when_not_bookmarked()
    {
        $video = Video::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($video->isBookmarkedBy($user->id));
    }

    /**
     * Test isBookmarkedBy returns false for null user
     */
    public function test_is_bookmarked_by_returns_false_for_null_user()
    {
        $video = Video::factory()->create();

        $this->assertFalse($video->isBookmarkedBy(null));
    }

    /**
     * Test menu_data is cast to array
     */
    public function test_menu_data_is_cast_to_array()
    {
        $video = Video::factory()->create([
            'menu_data' => json_encode(['dish' => 'Nasi Goreng', 'calories' => 500])
        ]);

        $this->assertIsArray($video->menu_data);
        $this->assertEquals('Nasi Goreng', $video->menu_data['dish']);
        $this->assertEquals(500, $video->menu_data['calories']);
    }

    /**
     * Test video fillable attributes
     */
    public function test_video_fillable_attributes()
    {
        $user = User::factory()->create();

        $video = Video::create([
            'user_id' => $user->id,
            's3_url' => 'https://example.com/video.mp4',
            'thumbnail_url' => 'https://example.com/thumb.jpg',
            'menu_data' => ['dish' => 'Pizza', 'calories' => 800],
        ]);

        $this->assertEquals($user->id, $video->user_id);
        $this->assertEquals('https://example.com/video.mp4', $video->s3_url);
        $this->assertEquals('https://example.com/thumb.jpg', $video->thumbnail_url);
        $this->assertEquals('Pizza', $video->menu_data['dish']);
    }
}
