<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Video;
use App\Models\View;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\View>
 */
class ViewFactory extends Factory
{
    protected $model = View::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'video_id' => Video::factory(),
            'ip_address' => fake()->ipv4(),
            'viewed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the view belongs to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the view belongs to a specific video.
     */
    public function forVideo(Video $video): static
    {
        return $this->state(fn (array $attributes) => [
            'video_id' => $video->id,
        ]);
    }

    /**
     * Indicate that the view was recent.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'viewed_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    /**
     * Indicate that the view was from a specific IP.
     */
    public function fromIp(string $ip): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => $ip,
        ]);
    }
}
