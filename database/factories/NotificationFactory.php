<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['like', 'comment', 'follow', 'mention'];
        $type = fake()->randomElement($types);

        return [
            'user_id' => User::factory(),
            'from_user_id' => User::factory(),
            'type' => $type,
            'title' => $this->getTitleForType($type),
            'message' => $this->getMessageForType($type),
            'video_id' => fake()->boolean(70) ? Video::factory() : null,
            'comment_id' => ($type === 'comment' && fake()->boolean(50)) ? Comment::factory() : null,
            'is_read' => fake()->boolean(30),
        ];
    }

    /**
     * Get title based on notification type.
     */
    private function getTitleForType(string $type): string
    {
        return match($type) {
            'like' => 'New Like',
            'comment' => 'New Comment',
            'follow' => 'New Follower',
            'mention' => 'You were mentioned',
            default => 'Notification',
        };
    }

    /**
     * Get message based on notification type.
     */
    private function getMessageForType(string $type): string
    {
        return match($type) {
            'like' => fake()->name() . ' liked your video',
            'comment' => fake()->name() . ' commented on your video',
            'follow' => fake()->name() . ' started following you',
            'mention' => fake()->name() . ' mentioned you in a comment',
            default => fake()->sentence(),
        };
    }

    /**
     * Indicate that the notification is for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the notification is from a specific user.
     */
    public function fromUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'from_user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the notification is related to a specific video.
     */
    public function forVideo(Video $video): static
    {
        return $this->state(fn (array $attributes) => [
            'video_id' => $video->id,
        ]);
    }

    /**
     * Indicate that the notification is related to a specific comment.
     */
    public function forComment(Comment $comment): static
    {
        return $this->state(fn (array $attributes) => [
            'comment_id' => $comment->id,
        ]);
    }

    /**
     * Create an unread notification.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }

    /**
     * Create a read notification.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Create a like notification.
     */
    public function like(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'like',
            'title' => 'New Like',
            'message' => fake()->name() . ' liked your video',
            'video_id' => Video::factory(),
            'comment_id' => null,
        ]);
    }

    /**
     * Create a comment notification.
     */
    public function comment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'comment',
            'title' => 'New Comment',
            'message' => fake()->name() . ' commented on your video',
            'video_id' => Video::factory(),
            'comment_id' => Comment::factory(),
        ]);
    }

    /**
     * Create a follow notification.
     */
    public function follow(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'follow',
            'title' => 'New Follower',
            'message' => fake()->name() . ' started following you',
            'video_id' => null,
            'comment_id' => null,
        ]);
    }
}
