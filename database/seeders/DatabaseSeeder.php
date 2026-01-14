<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password');
        $usedSlugs = [];

        $makeSlug = static function (string $name) use (&$usedSlugs): string {
            $base = Str::slug($name);
            if ($base === '') {
                $base = 'user';
            }
            $slug = $base;
            $counter = 2;
            while (in_array($slug, $usedSlugs, true) || User::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $counter;
                $counter += 1;
            }
            $usedSlugs[] = $slug;
            return $slug;
        };

        // Post slug generator (unique + stable)
        $usedPostSlugs = [];
        $makePostSlug = static function (string $title) use (&$usedPostSlugs): string {
            $base = Str::slug($title);
            if ($base === '') {
                $base = 'post';
            }
            $slug = $base;
            $counter = 2;

            while (in_array($slug, $usedPostSlugs, true) || Post::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $counter;
                $counter += 1;
            }

            $usedPostSlugs[] = $slug;
            return $slug;
        };

        $seedUsers = [
            'admin' => [
            'name' => 'Morgana O',
            'email' => 'admin@thehub.test',
            'role' => 'admin',
            'avatar' => '/images/avatar-default.svg',
            'bio' => 'Community ops and moderation. Keeps the hub safe and focused.',
        ],
        'dasha' => [
            'name' => 'Dasha N',
            'email' => 'dasha@thehub.test',
            'role' => 'maker',
            'avatar' => '/images/avatar-default.svg',
            'bio' => 'Hardware prototyper. Ships fast field tests and clean post-mortems.',
        ],
        'ilya' => [
            'name' => 'Ilya M',
            'email' => 'ilya@thehub.test',
            'role' => 'maker',
            'avatar' => '/images/avatar-default.svg',
            'bio' => 'Electronics builder. Focused on noise, power, and reliable boards.',
        ],
        'sveta' => [
            'name' => 'Sveta L',
            'email' => 'sveta@thehub.test',
            'role' => 'maker',
            'avatar' => '/images/avatar-default.svg',
            'bio' => 'Prototype UX systems and gesture-driven interfaces.',
        ],
        'timur' => [
            'name' => 'Timur K',
            'email' => 'timur@thehub.test',
            'role' => 'user',
            'avatar' => '/images/avatar-default.svg',
            'bio' => 'Student engineer exploring sensors and rapid validation.',
        ],
        'nikita' => [
            'name' => 'Nikita B',
            'email' => 'nikita@thehub.test',
            'role' => 'user',
            'avatar' => '/images/avatar-default.svg',
            'bio' => 'Writes short, practical reviews to keep teams moving.',
        ],
        'mila' => [
            'name' => 'Mila T',
            'email' => 'mila@thehub.test',
            'role' => 'maker',
            'avatar' => '/images/avatar-default.svg',
            'bio' => 'Content systems and calm reading workflows.',
        ],
        'katya' => [
            'name' => 'Katya F',
            'email' => 'katya@thehub.test',
            'role' => 'user',
            'avatar' => '/images/avatar-default.svg',
            'bio' => 'Team lead who keeps projects scoped and documented.',
        ],
    ];

        $users = [];
        foreach ($seedUsers as $key => $data) {
            $existing = User::where('email', $data['email'])->first();
            if ($existing) {
                $users[$key] = $existing;
                if (!empty($existing->slug)) {
                    $usedSlugs[] = $existing->slug;
                } else {
                    $existing->slug = $makeSlug($data['name']);
                    $existing->save();
                }
                continue;
            }
            $users[$key] = User::factory()->create(array_merge($data, [
                'slug' => $makeSlug($data['name']),
                'password' => $password,
                'email_verified_at' => now(),
            ]));
        }

        $extraUsers = User::factory()
            ->count(6)
            ->create();

        User::query()->update([
            'avatar' => '/images/avatar-default.svg',
        ]);

        $estimateReadTime = static function (string $markdown): int {
            $wordCount = str_word_count(strip_tags($markdown));
            return max(1, (int) ceil($wordCount / 200));
        };

        // --- Base manual posts ---
        $posts = [
            [
                'user_key' => 'dasha',
                'type' => 'post',
                'slug' => 'power-hub-night',
                'title' => 'Power module for a field hub',
                'subtitle' => 'Night build: stabilized noise and heat without extra parts.',
                'status' => 'done',
                'tags' => ['hardware', 'power', 'night build'],
                'cover_url' => '/images/cover-gradient.svg',
                'body_markdown' => "## Context\nWe needed a stable 5V rail for sensors and a small compute board.\n\n## What changed\n- Added an LC filter at the input\n- Split clean and dirty ground\n- Shortened the sensor branch\n\n## Results\nNoise dropped and sensors stopped drifting.",
            ],
            [
                'user_key' => 'sveta',
                'type' => 'post',
                'slug' => 'weekend-gesture-board',
                'title' => 'Weekend gesture panel prototype',
                'subtitle' => 'Two days to test gesture control with real users.',
                'status' => 'done',
                'tags' => ['prototype', 'ux', 'sensors'],
                'cover_url' => '/images/cover-gradient.svg',
                'body_markdown' => "## Goal\nValidate a touchless interaction in two days.\n\n## Build\nRough frame, short wiring, and a simple feedback loop.\n\n## Takeaways\nStrict gestures beat clever gestures every time.",
            ],
            [
                'user_key' => 'mila',
                'type' => 'post',
                'slug' => 'field-notes',
                'title' => 'Field notes feed for a student team',
                'subtitle' => 'A calm reading flow so people can return without pressure.',
                'status' => 'in_progress',
                'tags' => ['writing', 'product', 'ux'],
                'cover_url' => '/images/cover-gradient.svg',
                'body_markdown' => "## Why\nChat logs were chaotic. We needed a calm archive.\n\n## Structure\nShort context blocks, followed by deeper detail.\n\n## Next\nAdd filters and a private read-later library.",
            ],
            [
                'user_key' => 'nikita',
                'type' => 'post',
                'slug' => 'fast-breakdown',
                'title' => 'Fast project reviews system',
                'subtitle' => 'A format that forces clarity in under 3 minutes.',
                'status' => 'paused',
                'tags' => ['review', 'process', 'motivation'],
                'cover_url' => '/images/cover-gradient.svg',
                'body_markdown' => "## Format\nThree short blocks: strong side, improvement, question.\n\n## Why\nShort reviews get done. Long reviews do not.\n\n## Status\nPaused until we automate reminders.",
            ],
            [
                'user_key' => 'timur',
                'type' => 'question',
                'slug' => 'read-time-metrics',
                'title' => 'How do you estimate read time for long posts?',
                'tags' => ['writing', 'metrics'],
                'body_markdown' => "I have long posts with code, tables, and diagrams.\n\nDo you use words-per-minute or treat images separately?",
            ],
            [
                'user_key' => 'ilya',
                'type' => 'question',
                'slug' => 'pcb-power-noise',
                'title' => 'Best practices for power noise on mixed-signal PCBs?',
                'tags' => ['hardware', 'pcb'],
                'body_markdown' => "Mixed-signal board shows spikes when sensors boot.\n\nAny default layout or grounding rules you follow?",
            ],
        ];

        foreach ($posts as $post) {
            $markdown = $post['body_markdown'] ?? '';
            $readTime = $estimateReadTime($markdown);

            $existingPost = Post::where('slug', $post['slug'])->first();
            if ($existingPost) {
                continue;
            }

            Post::create([
                'user_id' => $users[$post['user_key']]->id,
                'type' => $post['type'],
                'slug' => $post['slug'],
                'title' => $post['title'],
                'subtitle' => $post['subtitle'] ?? null,
                'body_markdown' => $markdown,
                'body_html' => null,
                'media_url' => $post['media_url'] ?? null,
                'cover_url' => $post['cover_url'] ?? null,
                'status' => $post['type'] === 'question' ? null : ($post['status'] ?? null),
                'tags' => $post['tags'] ?? [],
                'read_time_minutes' => $readTime,
            ]);
        }

        // --- Generate extra test posts (50+ posts) ---
        $desiredPostTotal = 55;
        $desiredQuestionTotal = 15;

        $allUsers = collect($users)->values()->merge($extraUsers)->values();
        $statusPool = ['done', 'in_progress', 'paused'];
        $tagPool = [
            'hardware', 'pcb', 'power', 'sensors', 'ux', 'product', 'writing', 'process',
            'firmware', 'testing', 'field', 'docs', 'review', 'signal', 'tools', 'debug',
            'battery', 'noise', 'measurement', 'prototype',
        ];

        $postTitles = [
            'Quick fixture for repeatable sensor tests',
            'Notes on reducing ADC jitter in a noisy build',
            'A simple checklist for field deployments',
            'Lessons from a failed enclosure print',
            'How we validated a buttonless UI',
            'What broke our boot time and how we fixed it',
            'Minimal logging that still tells the truth',
            'Power budget template for small devices',
            'Cable routing rules that saved our sanity',
            'A tiny “definition of done” for prototypes',
            'How we structure post-mortems for speed',
            'Measuring real battery life in the field',
            'Choosing pull-ups for I2C in messy wiring',
            'When to stop iterating and ship the test',
            'A calm way to tag and search project notes',
            'How we keep prototypes readable for others',
            'Noise hunting: what we check first',
            'Simple UI copy that reduces support questions',
            'A small rubric for peer reviews',
            'The fastest way to reproduce a bug on hardware',
            'Making sensor data debuggable with one CSV',
            'A fast way to compare 3 regulator options',
            'How we write “what we tried” notes that help later',
            'Debug diary: from random resets to a single root cause',
            'Simple shielding tricks that do not lie',
        ];

        $questionTitles = [
            'Best way to document wiring changes between revisions?',
            'How do you store calibration values safely?',
            'Do you prefer SPI or I2C for short runs and why?',
            'What’s your default approach to debounce?',
            'How do you avoid scope creep in week-long prototypes?',
            'What is your go-to method for measuring noise?',
            'How do you handle “unknown unknowns” in field tests?',
            'How do you write posts that people actually read?',
            'Any simple way to track experiments and results?',
            'What is your process for choosing sensors quickly?',
            'Do you keep separate logs for analog and digital issues?',
            'How do you decide a prototype is “good enough” to show?',
        ];

        $subtitlePool = [
            'Short build, clean notes, and a result you can repeat.',
            'Small change, big stability improvement.',
            'A practical pattern you can copy into your project.',
            'This took less time than debugging later.',
            'We kept it simple and it worked.',
            'No heroics: just a method.',
            'The boring approach that scales.',
            'This is what we would do again.',
        ];

        $currentPostCount = Post::where('type', 'post')->count();
        $currentQuestionCount = Post::where('type', 'question')->count();
        $postsToCreate = max(0, $desiredPostTotal - $currentPostCount);
        $questionsToCreate = max(0, $desiredQuestionTotal - $currentQuestionCount);
        $toCreate = $postsToCreate + $questionsToCreate;

        for ($i = 0; $i < $toCreate; $i++) {
            $isQuestion = false;
            if ($questionsToCreate > 0 && $postsToCreate > 0) {
                $isQuestion = random_int(1, 100) <= 25;
            } elseif ($questionsToCreate > 0) {
                $isQuestion = true;
            }

            if ($isQuestion) {
                $questionsToCreate -= 1;
            } else {
                $postsToCreate -= 1;
            }
            $type = $isQuestion ? 'question' : 'post';

            $title = $isQuestion
                ? $questionTitles[array_rand($questionTitles)]
                : $postTitles[array_rand($postTitles)];

            // Reduce duplicates a bit
            if (!$isQuestion && random_int(1, 100) <= 40) {
                $title .= ' #' . random_int(2, 12);
            }

            $slug = $makePostSlug($title);

            $subtitle = $isQuestion ? null : $subtitlePool[array_rand($subtitlePool)];
            $status = $isQuestion ? null : $statusPool[array_rand($statusPool)];

            $tagCount = random_int(2, 5);
            $pool = $tagPool;
            shuffle($pool);
            $tags = array_slice($pool, 0, $tagCount);

            $markdown = $isQuestion
                ? "## Question\n{$title}\n\n## Context\n- What I already tried\n- Constraints (time, parts, power)\n\n## What I need\nA default approach or a small checklist I can apply."
                : "## Context\nWhat we needed and what was failing.\n\n## Approach\n- Constraints\n- The smallest change that could work\n- What we measured\n\n## Results\nWhat improved, what stayed messy, and what we would do next time.\n\n## Notes\nTools, settings, and pitfalls for a repeatable test.";

            $readTime = $estimateReadTime($markdown);

            $author = $allUsers->random();

            Post::create([
                'user_id' => $author->id,
                'type' => $type,
                'slug' => $slug,
                'title' => $title,
                'subtitle' => $subtitle,
                'body_markdown' => $markdown,
                'body_html' => null,
                'media_url' => null,
                'cover_url' => '/images/cover-gradient.svg',
                'status' => $status,
                'tags' => $tags,
                'read_time_minutes' => $readTime,
            ]);
        }

        // --- Comments (manual seeds) ---
        $commentSeeds = [
            [
                'post_slug' => 'power-hub-night',
                'user_key' => 'timur',
                'body' => 'Super relatable. Which regulator did you settle on?',
                'section' => 'Context and constraints',
                'useful' => 5,
            ],
            [
                'post_slug' => 'power-hub-night',
                'user_key' => 'ilya',
                'body' => 'Thanks for the clean write-up. The ground split tip is solid.',
                'section' => 'Measurements',
                'useful' => 3,
            ],
            [
                'post_slug' => 'weekend-gesture-board',
                'user_key' => 'katya',
                'body' => 'Can you share the wiring diagram?',
                'section' => 'Build',
                'useful' => 2,
            ],
            [
                'key' => 'rtm-answer',
                'post_slug' => 'read-time-metrics',
                'user_key' => 'dasha',
                'body' => 'I start with 180 words/min and add 30 seconds per figure.',
                'useful' => 18,
            ],
            [
                'post_slug' => 'read-time-metrics',
                'user_key' => 'timur',
                'body' => 'Do you count dense tables differently?',
                'parent_key' => 'rtm-answer',
                'useful' => 3,
            ],
            [
                'post_slug' => 'pcb-power-noise',
                'user_key' => 'sveta',
                'body' => 'Split analog and digital ground, then connect near the ADC.',
                'useful' => 7,
            ],
        ];

        $commentIds = [];
        foreach ($commentSeeds as $seed) {
            $parentId = null;
            if (!empty($seed['parent_key']) && isset($commentIds[$seed['parent_key']])) {
                $parentId = $commentIds[$seed['parent_key']];
            }

            $comment = PostComment::firstOrCreate([
                'post_slug' => $seed['post_slug'],
                'user_id' => $users[$seed['user_key']]->id,
                'body' => $seed['body'],
                'section' => $seed['section'] ?? null,
                'useful' => $seed['useful'] ?? 0,
                'parent_id' => $parentId,
            ]);

            if (!empty($seed['key'])) {
                $commentIds[$seed['key']] = $comment->id;
            }
        }

        // --- Reviews ---
        $reviewSeeds = [
            [
                'post_slug' => 'power-hub-night',
                'user_key' => 'ilya',
                'improve' => 'Add a small comparison table for the three variants.',
                'why' => 'Readers can compare noise and heat at a glance.',
                'how' => 'One table after the measurements section with three rows.',
            ],
            [
                'post_slug' => 'fast-breakdown',
                'user_key' => 'mila',
                'improve' => 'Include a 3-question template card in the review flow.',
                'why' => 'Prompts reduce blank-page friction for new reviewers.',
                'how' => 'Add a card preview below the intro block.',
            ],
        ];

        foreach ($reviewSeeds as $seed) {
            PostReview::firstOrCreate([
                'post_slug' => $seed['post_slug'],
                'user_id' => $users[$seed['user_key']]->id,
                'improve' => $seed['improve'],
                'why' => $seed['why'],
                'how' => $seed['how'],
            ]);
        }

        // Map posts for relations
        $postMap = Post::query()->get(['id', 'slug'])->keyBy('slug');

        // --- Upvotes ---
        $upvotes = [
            ['user_key' => 'timur', 'post_slug' => 'power-hub-night'],
            ['user_key' => 'katya', 'post_slug' => 'power-hub-night'],
            ['user_key' => 'mila', 'post_slug' => 'weekend-gesture-board'],
            ['user_key' => 'dasha', 'post_slug' => 'fast-breakdown'],
            ['user_key' => 'sveta', 'post_slug' => 'read-time-metrics'],
        ];

        foreach ($upvotes as $vote) {
            $postId = $postMap[$vote['post_slug']]->id ?? null;
            if (!$postId) {
                continue;
            }
            DB::table('post_upvotes')->insertOrIgnore([
                'user_id' => $users[$vote['user_key']]->id,
                'post_id' => $postId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- Saves ---
        $saves = [
            ['user_key' => 'timur', 'post_slug' => 'fast-breakdown'],
            ['user_key' => 'katya', 'post_slug' => 'power-hub-night'],
            ['user_key' => 'mila', 'post_slug' => 'field-notes'],
        ];

        foreach ($saves as $save) {
            $postId = $postMap[$save['post_slug']]->id ?? null;
            if (!$postId) {
                continue;
            }
            DB::table('post_saves')->insertOrIgnore([
                'user_id' => $users[$save['user_key']]->id,
                'post_id' => $postId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- Follows ---
        $follows = [
            ['follower' => 'timur', 'following' => 'dasha'],
            ['follower' => 'katya', 'following' => 'mila'],
            ['follower' => 'nikita', 'following' => 'sveta'],
            ['follower' => 'mila', 'following' => 'dasha'],
        ];

        foreach ($follows as $follow) {
            DB::table('user_follows')->insertOrIgnore([
                'follower_id' => $users[$follow['follower']]->id,
                'following_id' => $users[$follow['following']]->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- Reading progress (FIXED: post_id must be numeric id) ---
        $readingProgress = [
            ['user_key' => 'timur', 'post_slug' => 'power-hub-night', 'percent' => 42, 'anchor' => 'context'],
            ['user_key' => 'katya', 'post_slug' => 'field-notes', 'percent' => 68, 'anchor' => 'structure'],
            ['user_key' => 'mila', 'post_slug' => 'fast-breakdown', 'percent' => 15, 'anchor' => 'format'],
        ];

        foreach ($readingProgress as $progress) {
            $postId = $postMap[$progress['post_slug']]->id ?? null;
            if (!$postId) {
                continue;
            }

            DB::table('reading_progress')->updateOrInsert(
                ['user_id' => $users[$progress['user_key']]->id, 'post_id' => $postId],
                [
                    'percent' => $progress['percent'],
                    'anchor' => $progress['anchor'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $this->call(UserBadgeSeeder::class);
        $this->call(DemoActivitySeeder::class);
    }
}
