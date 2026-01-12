<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);
        $slugBase = Str::slug($title);
        if ($slugBase === '') {
            $slugBase = 'post';
        }
        $slug = $slugBase . '-' . Str::lower(Str::random(6));
        $body = fake()->paragraphs(3, true);
        $readMinutes = max(1, (int) ceil(str_word_count($body) / 200));

        $existingUserId = User::query()->inRandomOrder()->value('id');

        return [
            'user_id' => $existingUserId ?? User::factory(),
            'type' => 'post',
            'slug' => $slug,
            'title' => $title,
            'subtitle' => fake()->sentence(10),
            'body_markdown' => $body,
            'body_html' => null,
            'media_url' => null,
            'cover_url' => null,
            'status' => 'in_progress',
            'tags' => fake()->randomElements(['hardware', 'ux', 'prototype', 'process', 'writing'], 2),
            'read_time_minutes' => $readMinutes,
        ];
    }

    public function question(): static
    {
        return $this->state(fn () => [
            'type' => 'question',
            'status' => null,
        ]);
    }
}
