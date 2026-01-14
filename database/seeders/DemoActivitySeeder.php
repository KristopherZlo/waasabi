<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $faker = fake();
        $users = User::query()->get();
        $targetUsers = 20;

        $missingUsers = max(0, $targetUsers - $users->count());
        if ($missingUsers > 0) {
            User::factory()->count($missingUsers)->create([
                'role' => 'user',
                'avatar' => '/images/avatar-default.svg',
                'password' => Hash::make('password'),
            ]);
            $users = User::query()->get();
        }

        if (!Schema::hasTable('posts')) {
            return;
        }

        $targetPosts = 24;
        $targetQuestions = 10;
        $postCount = Post::query()->where('type', 'post')->count();
        $questionCount = Post::query()->where('type', 'question')->count();

        $missingPosts = max(0, $targetPosts - $postCount);
        if ($missingPosts > 0) {
            Post::factory()
                ->count($missingPosts)
                ->state(function () use ($users, $faker) {
                    return [
                        'type' => 'post',
                        'user_id' => $users->random()->id,
                        'status' => $faker->randomElement(['in_progress', 'done', 'paused']),
                        'cover_url' => '/images/cover-gradient.svg',
                    ];
                })
                ->create();
        }

        $missingQuestions = max(0, $targetQuestions - $questionCount);
        if ($missingQuestions > 0) {
            Post::factory()
                ->count($missingQuestions)
                ->question()
                ->state(function () use ($users) {
                    return [
                        'user_id' => $users->random()->id,
                        'cover_url' => null,
                        'subtitle' => null,
                    ];
                })
                ->create();
        }

        $posts = Post::query()->get(['id', 'slug', 'type']);

        if (Schema::hasTable('post_comments')) {
            foreach ($posts as $post) {
                $existing = PostComment::query()->where('post_slug', $post->slug)->count();
                $target = $post->type === 'question'
                    ? $faker->numberBetween(3, 6)
                    : $faker->numberBetween(2, 5);
                $toCreate = max(0, $target - $existing);
                if ($toCreate <= 0) {
                    continue;
                }

                $parents = [];
                for ($i = 0; $i < $toCreate; $i++) {
                    $user = $users->random();
                    $createdAt = now()->subMinutes($faker->numberBetween(10, 6000));
                    $comment = PostComment::create([
                        'post_slug' => $post->slug,
                        'user_id' => $user->id,
                        'body' => $faker->sentences($faker->numberBetween(1, 2), true),
                        'section' => $post->type === 'post' && $faker->boolean(35)
                            ? $faker->words(2, true)
                            : null,
                        'useful' => $faker->numberBetween(0, 24),
                        'parent_id' => null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                    if ($post->type === 'question' && $i < 2) {
                        $parents[] = $comment->id;
                    }
                }

                if ($post->type === 'question' && $parents) {
                    foreach ($parents as $parentId) {
                        $replyCount = $faker->numberBetween(1, 2);
                        for ($i = 0; $i < $replyCount; $i++) {
                            $user = $users->random();
                            $createdAt = now()->subMinutes($faker->numberBetween(5, 3000));
                            PostComment::create([
                                'post_slug' => $post->slug,
                                'user_id' => $user->id,
                                'body' => $faker->sentence(12),
                                'section' => null,
                                'useful' => $faker->numberBetween(0, 16),
                                'parent_id' => $parentId,
                                'created_at' => $createdAt,
                                'updated_at' => $createdAt,
                            ]);
                        }
                    }
                }
            }
        }

        if (Schema::hasTable('post_reviews')) {
            foreach ($posts->where('type', 'post') as $post) {
                $existing = PostReview::query()->where('post_slug', $post->slug)->count();
                if ($existing > 0 || !$faker->boolean(55)) {
                    continue;
                }
                $user = $users->random();
                $createdAt = now()->subMinutes($faker->numberBetween(30, 8000));
                PostReview::create([
                    'post_slug' => $post->slug,
                    'user_id' => $user->id,
                    'improve' => $faker->sentence(10),
                    'why' => $faker->sentence(12),
                    'how' => $faker->sentence(14),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        if (Schema::hasTable('post_upvotes')) {
            foreach ($posts as $post) {
                $maxVotes = min(12, $users->count());
                $count = $maxVotes > 0 ? $faker->numberBetween(0, $maxVotes) : 0;
                if ($count <= 0) {
                    continue;
                }
                $voters = $users->shuffle()->take($count);
                foreach ($voters as $voter) {
                    DB::table('post_upvotes')->insertOrIgnore([
                        'user_id' => $voter->id,
                        'post_id' => $post->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('post_saves')) {
            foreach ($posts->where('type', 'post') as $post) {
                $maxSaves = min(4, $users->count());
                $count = $maxSaves > 0 ? $faker->numberBetween(0, $maxSaves) : 0;
                if ($count <= 0) {
                    continue;
                }
                $savers = $users->shuffle()->take($count);
                foreach ($savers as $saver) {
                    DB::table('post_saves')->insertOrIgnore([
                        'user_id' => $saver->id,
                        'post_id' => $post->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('user_follows')) {
            foreach ($users as $user) {
                $others = $users->where('id', '!=', $user->id)->values();
                if ($others->isEmpty()) {
                    continue;
                }
                $count = $faker->numberBetween(0, min(3, $others->count()));
                if ($count <= 0) {
                    continue;
                }
                $targets = $others->shuffle()->take($count);
                foreach ($targets as $target) {
                    DB::table('user_follows')->insertOrIgnore([
                        'follower_id' => $user->id,
                        'following_id' => $target->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('reading_progress')) {
            $targets = $posts->where('type', 'post')->shuffle()->take(min(10, $posts->count()));
            foreach ($targets as $post) {
                $user = $users->random();
                $percent = $faker->numberBetween(5, 95);
                DB::table('reading_progress')->updateOrInsert(
                    ['user_id' => $user->id, 'post_id' => $post->slug],
                    [
                        'percent' => $percent,
                        'anchor' => $faker->word(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        if (Schema::hasTable('content_reports')) {
            $reportTargets = $posts->where('type', 'post')->shuffle()->take(min(6, $posts->count()));
            foreach ($reportTargets as $post) {
                $reportCount = $faker->numberBetween(0, 3);
                for ($i = 0; $i < $reportCount; $i++) {
                    $reporter = $users->random();
                    $createdAt = now()->subMinutes($faker->numberBetween(20, 9000));
                    DB::table('content_reports')->insert([
                        'user_id' => $reporter->id,
                        'content_type' => 'post',
                        'content_id' => (string) $post->id,
                        'content_url' => '/projects/' . $post->slug,
                        'reason' => $faker->randomElement(['spam', 'abuse', 'offtopic', 'other']),
                        'details' => $faker->boolean(60) ? $faker->sentence(10) : null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
        }
    }
}
