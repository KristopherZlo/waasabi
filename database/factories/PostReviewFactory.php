<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostReview>
 */
class PostReviewFactory extends Factory
{
    protected $model = PostReview::class;

    public function definition(): array
    {
        $post = Post::query()->inRandomOrder()->first() ?? Post::factory()->create();

        return [
            'post_id' => $post->id,
            'post_slug' => $post->slug,
            'user_id' => User::factory(),
            'improve' => fake()->sentence(10),
            'why' => fake()->sentence(12),
            'how' => fake()->sentence(12),
        ];
    }
}
