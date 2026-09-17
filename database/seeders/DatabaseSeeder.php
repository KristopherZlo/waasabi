<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\ProjectMember;
use App\Models\ProjectUpdate;
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
        $password = Hash::make(Str::random(40));
        $usedSlugs = [];

        $makeSlug = static function (string $name) use (&$usedSlugs): string {
            $base = Str::slug($name);
            if ($base === '') {
                $base = 'user';
            }
            $slug = $base;
            $counter = 2;
            while (in_array($slug, $usedSlugs, true) || User::where('slug', $slug)->exists()) {
                $slug = $base.'-'.$counter;
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
                $slug = $base.'-'.$counter;
                $counter += 1;
            }

            $usedPostSlugs[] = $slug;

            return $slug;
        };

        $seedUsers = [
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
            'artem' => [
                'name' => 'Artem Volkov',
                'email' => 'artem@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Furniture maker documenting jigs, finishes, and repairable construction.',
            ],
            'lena' => [
                'name' => 'Lena Ortiz',
                'email' => 'lena@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Data visualization designer interested in public-interest tools.',
            ],
            'noora' => [
                'name' => 'Noora Laine',
                'email' => 'noora@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Ceramic artist testing repeatable glazes and low-waste studio methods.',
            ],
            'emil' => [
                'name' => 'Emil Saar',
                'email' => 'emil@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Small-game developer focused on playful systems and accessible controls.',
            ],
            'vera' => [
                'name' => 'Vera Kim',
                'email' => 'vera@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Documentary photographer building careful community archives.',
            ],
            'anton' => [
                'name' => 'Anton Reyes',
                'email' => 'anton@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Mechanical prototyper. Mostly enclosures, fixtures, and 3D printing failures.',
            ],
            'sofia' => [
                'name' => 'Sofia Berg',
                'email' => 'sofia@thehub.test',
                'role' => 'user',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Teacher adapting open tools for small classrooms and workshops.',
            ],
            'leo' => [
                'name' => 'Leo Martins',
                'email' => 'leo@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Open-source maintainer working on offline-first community software.',
            ],
            'aino' => [
                'name' => 'Aino Kallio',
                'email' => 'aino@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Textile artist mixing hand embroidery with small data stories.',
            ],
            'mika' => [
                'name' => 'Mika Chen',
                'email' => 'mika@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Robotics builder who cares about maintainable mechanisms and safe demos.',
            ],
            'robin' => [
                'name' => 'Robin Adeyemi',
                'email' => 'robin@thehub.test',
                'role' => 'user',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Community climate researcher collecting small, useful local datasets.',
            ],
            'maja' => [
                'name' => 'Maja Lind',
                'email' => 'maja@thehub.test',
                'role' => 'maker',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Sound designer recording ordinary places and unusual instruments.',
            ],
            'samir' => [
                'name' => 'Samir Patel',
                'email' => 'samir@thehub.test',
                'role' => 'user',
                'avatar' => '/images/avatar-default.svg',
                'bio' => 'Volunteer organizer improving onboarding, schedules, and shared documentation.',
            ],
        ];

        $users = [];
        foreach ($seedUsers as $key => $data) {
            $existing = User::where('email', $data['email'])->first();
            if ($existing) {
                $users[$key] = $existing;
                if (! empty($existing->slug)) {
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
            ->create(['password' => $password]);

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
                'user_key' => 'mila',
                'type' => 'post',
                'slug' => 'collab-quiet-dashboard',
                'title' => 'Looking for a UI designer for a calm project dashboard',
                'subtitle' => 'Looking for: UI designer to shape the dashboard and visual system.',
                'status' => 'in_progress',
                'tags' => ['design', 'ui', 'product'],
                'cover_url' => '/images/cover-gradient.svg',
                'body_markdown' => "## About the project\nWe are building a calm dashboard for student teams to track weekly progress without noise.\n\n## What we need\n- Visual system and spacing rules\n- Reusable components for dashboards\n- A clean, readable layout\n\n## Time\n4-6 hours/week for 3-4 weeks. Remote. DM on profile if interested.",
            ],
            [
                'user_key' => 'ilya',
                'type' => 'post',
                'slug' => 'collab-sensor-kit',
                'title' => 'Need an embedded developer for a battery sensor kit',
                'subtitle' => 'Looking for: embedded dev to ship firmware for a sensor kit.',
                'status' => 'in_progress',
                'tags' => ['firmware', 'hardware', 'battery'],
                'cover_url' => '/images/cover-gradient.svg',
                'body_markdown' => "## About the project\nWe have a working prototype and need help polishing the firmware.\n\n## Scope\n- Low power sleep cycle\n- Sensor polling and debounce\n- Simple radio packet format\n\n## Time\n5-8 hours/week. Remote.",
            ],
            [
                'user_key' => 'katya',
                'type' => 'post',
                'slug' => 'collab-community-docs',
                'title' => 'Seeking a technical writer to document our build',
                'subtitle' => 'Looking for: technical writer to turn build notes into guides.',
                'status' => 'in_progress',
                'tags' => ['writing', 'docs', 'community'],
                'cover_url' => '/images/cover-gradient.svg',
                'body_markdown' => "## About the project\nWe have strong build notes but they are too chaotic for new contributors.\n\n## What we need\n- Turn notes into a structured guide\n- Clear summaries and screenshots\n- A consistent tone for new contributors\n\n## Time\n3-5 hours/week. Remote.",
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

        $longFormPosts = require database_path('seeders/data/longform_posts.php');

        foreach ($longFormPosts as $post) {
            $markdown = $post['body_markdown'] ?? '';
            $readTime = $estimateReadTime($markdown);
            $type = $post['type'] ?? 'post';
            $slug = $post['slug'] ?? $makePostSlug($post['title']);

            if (Post::where('slug', $slug)->exists()) {
                continue;
            }

            Post::create([
                'user_id' => $users[$post['user_key']]->id,
                'type' => $type,
                'slug' => $slug,
                'title' => $post['title'],
                'subtitle' => $post['subtitle'] ?? null,
                'body_markdown' => $markdown,
                'body_html' => null,
                'media_url' => $post['media_url'] ?? null,
                'cover_url' => $post['cover_url'] ?? null,
                'status' => $type === 'question' ? null : ($post['status'] ?? null),
                'tags' => $post['tags'] ?? [],
                'read_time_minutes' => $readTime,
            ]);
        }

        $this->call(LivingForumSeeder::class);

        Post::query()->where('type', 'question')->update(['is_project' => false]);
        Post::query()->whereIn('slug', ['fast-breakdown', 'night-bus-photo-essay', 'procedural-moss-game'])
            ->update(['is_project' => false]);
        ProjectUpdate::query()->whereHas('post', fn ($query) => $query->where('is_project', false))->delete();
        ProjectMember::query()->whereHas('post', fn ($query) => $query->where('is_project', false))->delete();

        $postMap = Post::query()->get(['id', 'slug'])->keyBy('slug');

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
            if (! empty($seed['parent_key']) && isset($commentIds[$seed['parent_key']])) {
                $parentId = $commentIds[$seed['parent_key']];
            }

            $comment = PostComment::firstOrCreate([
                'post_id' => $postMap[$seed['post_slug']]->id,
                'post_slug' => $seed['post_slug'],
                'user_id' => $users[$seed['user_key']]->id,
                'body' => $seed['body'],
                'section' => $seed['section'] ?? null,
                'useful' => $seed['useful'] ?? 0,
                'parent_id' => $parentId,
            ]);

            if (! empty($seed['key'])) {
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
                'post_id' => $postMap[$seed['post_slug']]->id,
                'post_slug' => $seed['post_slug'],
                'user_id' => $users[$seed['user_key']]->id,
                'improve' => $seed['improve'],
                'why' => $seed['why'],
                'how' => $seed['how'],
            ]);
        }

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
            if (! $postId) {
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
            if (! $postId) {
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
            if (! $postId) {
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

        $this->call(CollaborationSeeder::class);
    }
}
