<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostComment>
 */
class PostCommentFactory extends Factory
{
    protected $model = PostComment::class;

    public function definition(): array
    {
        return [
            'post_slug' => function () {
                $post = Post::query()->inRandomOrder()->first() ?? Post::factory()->create();
                return $post->slug;
            },
            'user_id' => User::factory(),
            'body' => fake()->sentence(12),
            'section' => fake()->optional()->words(2, true),
            'useful' => fake()->numberBetween(0, 10),
            'parent_id' => null,
        ];
    }
}
