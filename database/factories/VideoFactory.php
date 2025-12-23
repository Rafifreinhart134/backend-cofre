<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Video>
 */
class VideoFactory extends Factory
{
    protected $model = Video::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            's3_url' => fake()->url() . '/video_' . fake()->uuid() . '.mp4',
            'thumbnail_url' => fake()->imageUrl(640, 480, 'video', true),
            'menu_data' => [
                'title' => fake()->sentence(3),
                'description' => fake()->paragraph(),
                'tags' => fake()->words(3),
                'duration' => fake()->numberBetween(5, 300),
            ],
        ];
    }

    /**
     * Indicate that the video has a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the video has minimal menu data.
     */
    public function withMinimalData(): static
    {
        return $this->state(fn (array $attributes) => [
            'menu_data' => [
                'title' => fake()->sentence(2),
            ],
        ]);
    }
}
