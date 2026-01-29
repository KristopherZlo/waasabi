<?php

use App\Models\ContentReport;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\SupportTicket;
use App\Models\TopbarPromo;
use App\Models\User;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileSettingsController;
use App\Http\Controllers\ProfileBadgeController;
use App\Http\Controllers\ProfileFollowController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReadLaterController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Services\AutoModerationService;
use App\Services\BadgePayloadService;
use App\Services\BadgeCatalogService;
use App\Services\ContentModerationService;
use App\Services\FeedService;
use App\Services\ImageUploadService;
use App\Services\VisibilityService;
use App\Services\MakerPromotionService;
use App\Services\ModerationService;
use App\Services\TextModerationService;
use App\Services\TopbarPromoService;
use App\Services\UserSlugService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

$badgeCatalog = app(BadgeCatalogService::class)->all();

$generateUserSlug = static function (string $name): string {
    return app(UserSlugService::class)->generate($name);
};

$preparePostStats = static function (iterable $posts): array {
    return FeedService::preparePostStats($posts, Auth::user());
};

$projects = [
    [
        'slug' => 'power-hub-night',
        'title' => 'Power module for a field hub',
        'subtitle' => 'Night build: stabilized noise and heat without extra parts.',
        'context' => 'fought power noise and finally stabilized the rail',
        'published' => '1 hour ago',
        'published_minutes' => 60,
        'score' => 128,
        'returns' => 14,
        'saves' => 5,
        'read_time' => '10 min',
        'read_time_minutes' => 10,
        'cover' => '/images/cover-gradient.svg',
        'media' => 'media--pulse',
        'status_key' => 'done',
        'status' => __('ui.project.status_done'),
        'tags' => ['hardware', 'power', 'night build'],
        'author' => ['name' => 'Dasha N.', 'role' => 'maker', 'avatar' => '/images/avatar-1.svg'],
        'comments' => [
            ['author' => 'Ira P.', 'time' => '2 hours ago', 'section' => 'Context and constraints', 'text' => 'Super relatable. Which regulator did you settle on?', 'useful' => 5, 'role' => 'user'],
            ['author' => 'Misha G.', 'time' => 'yesterday', 'section' => 'Measurements', 'text' => 'Same pain here. Thanks for a clean write-up.', 'useful' => 3, 'role' => 'maker'],
        ],
        'reviews' => [
            [
                'author' => ['name' => 'Ilya M.', 'role' => 'Maker', 'avatar' => '/images/avatar-4.svg', 'note' => '12 reviews in electronics'],
                'time' => '1 hour ago',
                'improve' => 'Add a short table comparing the three variants with noise and heat metrics.',
                'why' => 'Readers can compare at a glance without scanning paragraphs.',
                'how' => 'One table after “Measurements and fixes” with 3 rows and 3 columns.',
            ],
        ],
        'reviewer' => ['name' => 'Ilya M.', 'role' => 'Maker', 'note' => '12 reviews in electronics', 'avatar' => '/images/avatar-4.svg'],
        'sections' => [
            [
                'title' => 'Context and constraints',
                'blocks' => [
                    ['type' => 'p', 'text' => 'This module powers a small field hub: sensors, telemetry, and a tiny compute board. Weight and heat were the main constraints, and the board had to run from a single battery pack.'],
                    ['type' => 'p', 'text' => 'In the field the supply is noisy: voltage dips, bursts, and ripple. On the first run the sensors drifted and the readings were useless.'],
                    ['type' => 'note', 'text' => 'Goal for iteration one: stable 5V and predictable heat on the enclosure.'],
                    ['type' => 'quote', 'text' => 'Without a stable rail, everything else is just noise.'],
                    ['type' => 'p', 'text' => 'So I decided to fix power first and postpone everything else. It was painful to skip features, but it saved the build.'],
                ],
            ],
            [
                'title' => 'What I changed and why',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I built three variants and logged noise, heat, and startup behavior. It was obvious that the layout was as important as the parts.'],
                    ['type' => 'h3', 'text' => 'Filtering'],
                    ['type' => 'p', 'text' => 'A small LC filter at the input and a strict split between dirty and clean ground helped more than expected. Short traces mattered.'],
                    ['type' => 'p', 'text' => 'I separated sensitive loads into their own branch and moved the regulator away from the sensor headers.'],
                    ['type' => 'note', 'text' => 'Every change went into a small measurement table to avoid guesswork.'],
                    ['type' => 'image', 'caption' => 'Measurement rig and three module variants.', 'src' => '/images/cover-gradient.svg'],
                ],
            ],
            [
                'title' => 'Measurements and fixes',
                'blocks' => [
                    ['type' => 'p', 'text' => 'A ground loop showed up only when sensors started together. Removing a single jumper reduced the spike by half.'],
                    ['type' => 'p', 'text' => 'After the fix the noise floor dropped and the readings stabilized within seconds.'],
                    ['type' => 'quote', 'text' => 'One jumper cost an hour, but saved the week.'],
                ],
            ],
            [
                'title' => 'Results and next step',
                'blocks' => [
                    ['type' => 'p', 'text' => 'The module now holds 5V with predictable heat and no sensor drift. It runs six hours on a pack without thermal runaway.'],
                    ['type' => 'p', 'text' => 'Next: lighter revision and reverse polarity protection.'],
                    ['type' => 'note', 'text' => 'If you have a similar setup, share your fix. I am collecting comparisons.'],
                ],
            ],
        ],
    ],
    [
        'slug' => 'weekend-gesture-board',
        'title' => 'Weekend gesture panel prototype',
        'subtitle' => 'Two days to test gesture control with real users.',
        'context' => 'built a fast gesture board to validate the interaction',
        'published' => '3 hours ago',
        'published_minutes' => 180,
        'score' => 97,
        'returns' => 9,
        'saves' => 4,
        'read_time' => '8 min',
        'read_time_minutes' => 8,
        'cover' => '/images/cover-gradient.svg',
        'media' => 'media--wire',
        'status_key' => 'done',
        'status' => __('ui.project.status_done'),
        'tags' => ['prototype', 'ux', 'sensors'],
        'author' => ['name' => 'Timur K.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
        'comments' => [
            ['author' => 'Nastya V.', 'time' => '3 hours ago', 'section' => 'Weekend build', 'text' => 'Looks great. Can you share the wiring diagram?', 'useful' => 2, 'role' => 'user'],
            ['author' => 'Petya D.', 'time' => 'yesterday', 'section' => 'Calibration and UX', 'text' => 'Any idea how to reduce false positives?', 'useful' => 1, 'role' => 'user'],
        ],
        'reviews' => [
            [
                'author' => ['name' => 'Sveta L.', 'role' => 'Maker', 'avatar' => '/images/avatar-5.svg', 'note' => '6 gesture prototypes shipped'],
                'time' => '2 hours ago',
                'improve' => 'Show two failure cases so readers see the edge of the sensor field.',
                'why' => 'People copy the happy path and then get surprised by noise.',
                'how' => 'Add a short clip or two photos with notes under "Calibration and UX".',
                'useful' => 4,
            ],
            [
                'author' => ['name' => 'Ilya M.', 'role' => 'Maker', 'avatar' => '/images/avatar-4.svg', 'note' => '12 reviews in electronics'],
                'time' => 'yesterday',
                'improve' => 'Add a quick calibration checklist for the two gestures.',
                'why' => 'It makes the prototype reproducible for other teams.',
                'how' => 'A 4-step list with timer values and distance hints.',
                'useful' => 2,
            ],
        ],
        'reviewer' => ['name' => 'Sveta L.', 'role' => 'Maker', 'note' => '6 gesture prototypes shipped', 'avatar' => '/images/avatar-5.svg'],
        'sections' => [
            [
                'title' => 'Why gestures and why fast',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I wanted touchless control with no menus. The fastest way was a two-day prototype with real users.'],
                    ['type' => 'p', 'text' => 'If it failed in a weekend, I would not waste months. The goal was signal, not polish.'],
                    ['type' => 'quote', 'text' => 'If we cannot make it usable in two days, it is not the right direction.'],
                ],
            ],
            [
                'title' => 'Build and setup',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I mounted the sensors in a simple frame and paired them with a tablet UI for quick feedback.'],
                    ['type' => 'p', 'text' => 'The layout was rough, but it kept wiring short and noise low.'],
                    ['type' => 'note', 'text' => 'Assembly speed was more important than aesthetics.'],
                    ['type' => 'image', 'caption' => 'Prototype panel and wiring.', 'src' => '/images/cover-gradient.svg'],
                ],
            ],
            [
                'title' => 'Calibration and UX',
                'blocks' => [
                    ['type' => 'p', 'text' => 'False triggers were the main issue. I reduced gesture space and kept only two actions.'],
                    ['type' => 'h3', 'text' => 'Feedback loop'],
                    ['type' => 'p', 'text' => 'A simple visual confirmation made users trust the system. Without it, they repeated gestures and caused errors.'],
                ],
            ],
            [
                'title' => 'Takeaways',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Gestures work if the system is strict and explicit. Ambiguity kills confidence.'],
                    ['type' => 'p', 'text' => 'Next I will try haptics and add a quick calibration step.'],
                ],
            ],
        ],
    ],
    [
        'slug' => 'field-notes',
        'title' => 'Field notes feed for a student team',
        'subtitle' => 'A calm reading flow so people can return without pressure.',
        'context' => 'built a feed to replace scattered chat logs',
        'published' => 'yesterday',
        'published_minutes' => 1440,
        'score' => 81,
        'returns' => 11,
        'saves' => 9,
        'read_time' => '6 min',
        'read_time_minutes' => 6,
        'cover' => '/images/cover-gradient.svg',
        'media' => 'media--grid',
        'status_key' => 'in-progress',
        'status' => __('ui.project.status_in_progress'),
        'tags' => ['writing', 'product', 'ux'],
        'author' => ['name' => 'Morgana O.', 'role' => 'user', 'avatar' => '/images/avatar-3.svg'],
        'comments' => [
            ['author' => 'Alina S.', 'time' => 'yesterday', 'section' => 'Reading flow', 'text' => 'The table of contents is exactly what we missed.', 'useful' => 4, 'role' => 'maker'],
            ['author' => 'Roma I.', 'time' => 'yesterday', 'section' => 'Return signals', 'text' => 'Bookmark + progress is a strong combo.', 'useful' => 2, 'role' => 'user'],
        ],
        'reviews' => [
            [
                'author' => ['name' => 'Mila T.', 'role' => 'Maker', 'avatar' => '/images/avatar-6.svg', 'note' => 'Content systems, 9 projects'],
                'time' => 'yesterday',
                'improve' => 'Add a simple "last read" stamp in the feed cards.',
                'why' => 'It reinforces the return habit and helps users pick where to resume.',
                'how' => 'Show a subtle line under the title with the last open time.',
                'useful' => 5,
            ],
            [
                'author' => ['name' => 'Alina S.', 'role' => 'Maker', 'avatar' => '/images/avatar-3.svg', 'note' => 'Reading flow researcher'],
                'time' => '2 days ago',
                'improve' => 'Clarify the difference between save and return signals.',
                'why' => 'Users might assume they do the same thing.',
                'how' => 'Add a short paragraph in "Return signals" with a concrete example.',
                'useful' => 3,
            ],
            [
                'author' => ['name' => 'Roma I.', 'role' => 'User', 'avatar' => '/images/avatar-2.svg', 'note' => 'Student team lead'],
                'time' => '3 days ago',
                'improve' => 'Provide a template for note entries to keep them consistent.',
                'why' => 'It will keep quality stable as the team grows.',
                'how' => 'Offer a short 3-field template in the publish flow.',
                'useful' => 1,
            ],
        ],
        'reviewer' => ['name' => 'Mila T.', 'role' => 'Maker', 'note' => 'Content systems, 9 projects', 'avatar' => '/images/avatar-6.svg'],
        'sections' => [
            [
                'title' => 'Why the feed',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Our team lived in chats. Messages expired after a day and context disappeared. We needed a calmer archive.'],
                    ['type' => 'p', 'text' => 'A feed is not chat. It stores context, lets you pause, and return without pressure.'],
                    ['type' => 'quote', 'text' => 'Reading should feel calm, not noisy.'],
                ],
            ],
            [
                'title' => 'How reading works',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Every note is a short block: context, action, question. Longer details live below.'],
                    ['type' => 'p', 'text' => 'Discussion is separated so it does not break the reading flow.'],
                    ['type' => 'h3', 'text' => 'Visual pauses'],
                    ['type' => 'p', 'text' => 'Quotes and callouts create breathing space in long text.'],
                    ['type' => 'note', 'text' => 'If text can be read in slices, people actually finish it.'],
                    ['type' => 'image', 'caption' => 'Example of a quiet reading layout.', 'src' => '/images/cover-gradient.svg'],
                ],
            ],
            [
                'title' => 'Return signals',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I track returns, saves, and inline reactions, not raw clicks.'],
                    ['type' => 'p', 'text' => 'Saving is stronger than a like. Returning a day later is even stronger.'],
                    ['type' => 'h3', 'text' => 'Why it matters'],
                    ['type' => 'p', 'text' => 'If someone returns, the text stays in their head. That is the real signal.'],
                ],
            ],
            [
                'title' => 'Next steps',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Add roles, project filters, and weight reactions by experience.'],
                    ['type' => 'p', 'text' => 'Ship a private read-later library with auto-resume.'],
                    ['type' => 'h3', 'text' => 'Minimal friction'],
                    ['type' => 'p', 'text' => 'Keep only reading and support. Everything else is optional.'],
                ],
            ],
        ],
    ],
    [
        'slug' => 'fast-breakdown',
        'title' => 'Fast project reviews system',
        'subtitle' => 'A format that forces clarity in under 3 minutes.',
        'context' => 'trying to make reviews short and useful',
        'published' => '4 days ago',
        'published_minutes' => 5760,
        'score' => 65,
        'returns' => 6,
        'saves' => 2,
        'read_time' => '9 min',
        'read_time_minutes' => 9,
        'cover' => '/images/cover-gradient.svg',
        'media' => 'media--pulse',
        'status_key' => 'paused',
        'status' => __('ui.project.status_paused'),
        'tags' => ['review', 'process', 'motivation'],
        'author' => ['name' => 'Nikita B.', 'role' => 'user', 'avatar' => '/images/avatar-7.svg'],
        'comments' => [
            ['author' => 'Oleg S.', 'time' => '6 hours ago', 'section' => '3-minute format', 'text' => 'Timers are a great constraint.', 'useful' => 3, 'role' => 'user'],
            ['author' => 'Katya F.', 'time' => 'yesterday', 'section' => 'Templates', 'text' => 'Cards format could work well here.', 'useful' => 1, 'role' => 'admin'],
        ],
        'reviews' => [
            [
                'author' => ['name' => 'Lena S.', 'role' => 'Maker', 'avatar' => '/images/avatar-8.svg', 'note' => '20 short reviews'],
                'time' => '3 days ago',
                'improve' => 'Add one real example review in the intro.',
                'why' => 'It will show the format faster than an explanation.',
                'how' => 'Place a 3-block card under "The pain of reviews".',
                'useful' => 6,
            ],
            [
                'author' => ['name' => 'Katya F.', 'role' => 'Admin', 'avatar' => '/images/avatar-7.svg', 'note' => 'Runs moderation'],
                'time' => '4 days ago',
                'improve' => 'Explain how you prevent low-effort answers.',
                'why' => 'Without guardrails people will write one-word replies.',
                'how' => 'Add a minimum word count and a sample response.',
                'useful' => 3,
            ],
            [
                'author' => ['name' => 'Oleg S.', 'role' => 'User', 'avatar' => '/images/avatar-1.svg', 'note' => 'Weekly reviewer'],
                'time' => 'last week',
                'improve' => 'Show how long a review usually takes in practice.',
                'why' => 'It sets expectations and increases adoption.',
                'how' => 'Add a small stat line: median 2.7 minutes.',
                'useful' => 2,
            ],
            [
                'author' => ['name' => 'Ira P.', 'role' => 'Maker', 'avatar' => '/images/avatar-4.svg', 'note' => 'Hardware reviews'],
                'time' => 'last week',
                'improve' => 'Include a variant for longer, complex projects.',
                'why' => 'Some projects need more context before feedback.',
                'how' => 'Offer an optional 4th block for constraints.',
                'useful' => 1,
            ],
        ],
        'reviewer' => ['name' => 'Lena S.', 'role' => 'Maker', 'note' => '20 short reviews', 'avatar' => '/images/avatar-8.svg'],
        'sections' => [
            [
                'title' => 'The pain of reviews',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Reviews tend to be long and heavy. People postpone them because they fear a long write-up.'],
                    ['type' => 'p', 'text' => 'I wanted a format that keeps flow and does not require mood.'],
                    ['type' => 'quote', 'text' => 'If a review cannot fit in 3 minutes, it will never happen.'],
                ],
            ],
            [
                'title' => 'Three blocks',
                'blocks' => [
                    ['type' => 'p', 'text' => 'The review is split into three blocks: strong side, improvement, question.'],
                    ['type' => 'p', 'text' => 'Each block is limited to two sentences. That keeps it readable.'],
                    ['type' => 'h3', 'text' => 'Template'],
                    ['type' => 'p', 'text' => 'The template keeps you from freezing. You just answer three questions.'],
                    ['type' => 'note', 'text' => 'Timers and hints help keep momentum.'],
                    ['type' => 'image', 'caption' => 'A quick review card example.', 'src' => '/images/cover-gradient.svg'],
                ],
            ],
            [
                'title' => 'Experiments',
                'blocks' => [
                    ['type' => 'p', 'text' => 'I tested several prompt styles, from strict to gentle. Strict prompts yielded more concrete feedback.'],
                    ['type' => 'p', 'text' => 'Showing reviewer experience helped trust, but needs validation.'],
                    ['type' => 'h3', 'text' => 'What worked'],
                    ['type' => 'p', 'text' => 'Timer plus example answer kept people moving.'],
                ],
            ],
            [
                'title' => 'Why I paused it',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Without automation the flow became manual and slow.'],
                    ['type' => 'p', 'text' => 'Next step is auto-prompts and a simple stats view.'],
                ],
            ],
        ],
    ],
];

$demoAuthors = [];
if (safeHasTable('users')) {
    $demoAuthors = User::query()
        ->orderBy('id')
        ->get()
        ->map(function (User $user) use ($generateUserSlug) {
            $name = $user->name ?? __('ui.project.anonymous');
            $slug = $user->slug ?? Str::slug($user->name ?? '');
            if ($slug === '') {
                $slug = $generateUserSlug($user->name ?? 'user');
            }
            return [
                'id' => $user->id,
                'name' => $name,
                'slug' => $slug,
                'role' => $user->role ?? 'user',
                'avatar' => $user->avatar ?? '/images/avatar-default.svg',
            ];
        })
        ->values()
        ->all();
}

$demoAuthorIndex = 0;
$nextDemoAuthor = static function () use (&$demoAuthorIndex, $demoAuthors) {
    if (!$demoAuthors) {
        return null;
    }
    $author = $demoAuthors[$demoAuthorIndex % count($demoAuthors)];
    $demoAuthorIndex += 1;
    return $author;
};

if (!empty($demoAuthors)) {
    $projects = array_map(static function (array $project) use ($nextDemoAuthor) {
        $author = $nextDemoAuthor();
        if ($author) {
            $project['author'] = $author;
        }
        return $project;
    }, $projects);
}

$profile = [
    'name' => 'Dasha N.',
    'slug' => 'dasha-n',
    'bio' => 'I build hardware prototypes and write concise post-mortems.',
    'role' => 'maker',
    'avatar' => '/images/avatar-1.svg',
    'quotes' => [
        '"I can see the work ? thanks for showing the process."',
        '"Great that you shipped it to a field test."',
    ],
];

$showcase = [
    ['title' => 'Shipped to the end', 'projects' => [$projects[0], $projects[1]]],
    ['title' => 'Living process', 'projects' => [$projects[2]]],
    ['title' => 'Strong reviews', 'projects' => [$projects[3]]],
];

$qa_questions = [
    [
        'slug' => 'read-time-metrics',
        'title' => 'How do you estimate read time for long posts?',
        'time' => '20:40',
        'published_minutes' => 40,
        'delta' => '+1',
        'tags' => ['writing', 'metrics'],
        'author' => ['name' => 'Ilya N.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
        'body' => "I have long project posts with code, tables, and diagrams.\n\nDo you use words-per-minute, or do you count code blocks and images separately? Looking for a simple rule of thumb.",
        'answers' => [
            [
                'author' => ['name' => 'Dasha N.', 'role' => 'maker', 'avatar' => '/images/avatar-1.svg'],
                'time' => '18 min ago',
                'text' => 'I start with 180 words/min and add ~30 seconds per figure/table. For code, I ignore it unless the block is long.',
                'score' => 18,
                'replies' => [
                    [
                        'author' => ['name' => 'Ilya N.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
                        'time' => '10 min ago',
                        'text' => 'Do you treat dense tables differently or same rule?',
                        'score' => 3,
                    ],
                    [
                        'author' => ['name' => 'Dasha N.', 'role' => 'maker', 'avatar' => '/images/avatar-1.svg'],
                        'time' => '6 min ago',
                        'text' => 'If it is more than a screen, I add 20-30 sec. Otherwise I keep it simple.',
                        'score' => 5,
                    ],
                ],
            ],
            [
                'author' => ['name' => 'Morgana O.', 'role' => 'admin', 'avatar' => '/images/avatar-3.svg'],
                'time' => '12 min ago',
                'text' => 'We just use words/200 and call it a day. It is not perfect, but it is consistent.',
                'score' => 9,
            ],
        ],
    ],
    [
        'slug' => 'pcb-power-noise',
        'title' => 'Best practices for power noise on mixed-signal PCBs?',
        'time' => '19:36',
        'published_minutes' => 64,
        'delta' => '+2',
        'tags' => ['hardware', 'pcb'],
        'author' => ['name' => 'Timur K.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
        'body' => "I keep getting noise spikes on mixed-signal boards when sensors boot.\n\nAny layout patterns or grounding rules you follow by default? I am trying to keep routing short, but still see spikes.",
        'answers' => [
            [
                'author' => ['name' => 'Ilya M.', 'role' => 'maker', 'avatar' => '/images/avatar-4.svg'],
                'time' => '32 min ago',
                'text' => 'Split analog and digital ground planes, then connect at one point near the ADC. Also keep the regulator physically away from the sensors.',
                'score' => 11,
            ],
            [
                'author' => ['name' => 'Sveta L.', 'role' => 'maker', 'avatar' => '/images/avatar-5.svg'],
                'time' => '25 min ago',
                'text' => 'Put decouplers as close as possible and avoid long sensor traces running parallel to switching lines.',
                'score' => 7,
            ],
            [
                'author' => ['name' => 'Petya D.', 'role' => 'user', 'avatar' => '/images/avatar-6.svg'],
                'time' => '18 min ago',
                'text' => 'LC filters on the sensor branch helped us a lot. Worth the extra parts.',
                'score' => 4,
            ],
        ],
    ],
    [
        'slug' => 'capstone-scope',
        'title' => 'How do you keep a capstone project scope realistic?',
        'time' => '18:30',
        'published_minutes' => 90,
        'delta' => '+5',
        'tags' => ['planning', 'school'],
        'author' => ['name' => 'Alina S.', 'role' => 'user', 'avatar' => '/images/avatar-7.svg'],
        'body' => "Our capstone team keeps adding features and the scope keeps growing.\n\nAny templates or rules you use to keep it realistic?",
        'answers' => [
            [
                'author' => ['name' => 'Lena S.', 'role' => 'maker', 'avatar' => '/images/avatar-8.svg'],
                'time' => '1 hour ago',
                'text' => 'We freeze scope after week 2, then allow only swaps. New feature must replace something of equal size.',
                'score' => 13,
            ],
            [
                'author' => ['name' => 'Nikita B.', 'role' => 'user', 'avatar' => '/images/avatar-7.svg'],
                'time' => '45 min ago',
                'text' => 'Timebox the MVP to 2 weeks. If it does not fit, cut until it does.',
                'score' => 6,
            ],
        ],
    ],
    [
        'slug' => 'lab-report-format',
        'title' => 'Do you publish lab reports as blog posts or PDFs?',
        'time' => '18:09',
        'published_minutes' => 111,
        'delta' => '+1',
        'tags' => ['writing', 'labs'],
        'author' => ['name' => 'Oleg S.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
        'body' => "We have lab work in PDFs, but nobody reads them.\n\nThinking of converting to a blog-like format. Has anyone done this?",
        'answers' => [
            [
                'author' => ['name' => 'Mila T.', 'role' => 'maker', 'avatar' => '/images/avatar-6.svg'],
                'time' => '52 min ago',
                'text' => 'We publish summaries as posts and keep the full PDF as an attachment. People read summaries, then open the PDF if needed.',
                'score' => 5,
            ],
        ],
    ],
    [
        'slug' => 'team-sync',
        'title' => 'Lightweight ways to keep a student team in sync?',
        'time' => '17:31',
        'published_minutes' => 129,
        'delta' => '+3',
        'tags' => ['team', 'process'],
        'author' => ['name' => 'Katya F.', 'role' => 'user', 'avatar' => '/images/avatar-4.svg'],
        'body' => "We are a 6-person team and keep losing context.\n\nAny lightweight rituals or tools that help without becoming overhead?",
        'answers' => [
            [
                'author' => ['name' => 'Morgana O.', 'role' => 'user', 'avatar' => '/images/avatar-3.svg'],
                'time' => '1 hour ago',
                'text' => 'Weekly 20-min demo with 3 bullets per person. We also keep a shared changelog doc.',
                'score' => 8,
            ],
            [
                'author' => ['name' => 'Timur K.', 'role' => 'user', 'avatar' => '/images/avatar-2.svg'],
                'time' => '40 min ago',
                'text' => 'We use a single “current status” note and update it every Friday. No extra meetings.',
                'score' => 4,
            ],
        ],
    ],
    [
        'slug' => 'prototype-bugs',
        'title' => 'Is it okay to ship a prototype with known bugs?',
        'time' => '16:58',
        'published_minutes' => 142,
        'delta' => '+2',
        'tags' => ['prototype', 'ethics'],
        'author' => ['name' => 'Nastya V.', 'role' => 'user', 'avatar' => '/images/avatar-5.svg'],
        'body' => "We have a demo next week and a few known bugs that do not affect the core flow.\n\nShould we ship or delay?",
        'answers' => [
            [
                'author' => ['name' => 'Ilya M.', 'role' => 'maker', 'avatar' => '/images/avatar-4.svg'],
                'time' => '35 min ago',
                'text' => 'Ship if you can explain the bugs and the core flow is stable. Demo is about learning, not perfection.',
                'score' => 10,
            ],
            [
                'author' => ['name' => 'Dasha N.', 'role' => 'maker', 'avatar' => '/images/avatar-1.svg'],
                'time' => '22 min ago',
                'text' => 'Make a short list of known bugs and keep the demo script away from them.',
                'score' => 6,
            ],
        ],
    ],
    [
        'slug' => 'long-post-structure',
        'title' => 'How do you structure long project posts with diagrams?',
        'time' => '16:22',
        'published_minutes' => 178,
        'delta' => '+1',
        'tags' => ['writing', 'docs'],
        'author' => ['name' => 'Roma I.', 'role' => 'user', 'avatar' => '/images/avatar-6.svg'],
        'body' => "My posts are long and diagram-heavy. Readers drop off.\n\nDo you interleave diagrams with short text blocks or collect them into one section?",
        'answers' => [
            [
                'author' => ['name' => 'Lena S.', 'role' => 'maker', 'avatar' => '/images/avatar-8.svg'],
                'time' => '28 min ago',
                'text' => 'Interleave small diagrams every 2–3 paragraphs. Readers stay in the flow.',
                'score' => 5,
            ],
        ],
    ],
    [
        'slug' => 'experiment-tracking',
        'title' => 'Tools for tracking experiments without slowing down?',
        'time' => '15:50',
        'published_minutes' => 190,
        'delta' => '+1',
        'tags' => ['process', 'notes'],
        'author' => ['name' => 'Misha G.', 'role' => 'user', 'avatar' => '/images/avatar-3.svg'],
        'body' => "We run quick experiments and keep losing notes.\n\nAny lightweight tools that do not slow you down?",
        'answers' => [
            [
                'author' => ['name' => 'Mila T.', 'role' => 'maker', 'avatar' => '/images/avatar-6.svg'],
                'time' => '55 min ago',
                'text' => 'We use a single page “experiment log” in Notion with three fields: date, goal, outcome.',
                'score' => 4,
            ],
        ],
    ],
];

if (!empty($demoAuthors)) {
    $qa_questions = array_map(static function (array $question) use ($nextDemoAuthor) {
        $author = $nextDemoAuthor();
        if ($author) {
            $question['author'] = $author;
        }
        return $question;
    }, $qa_questions);
}

$mapPostToProject = static function (Post $post, array $stats): array {
    return FeedService::mapPostToProjectWithStats($post, $stats);
};

$mapPostToQuestion = static function (Post $post, array $stats): array {
    return FeedService::mapPostToQuestionWithStats($post, $stats);
};

$postSlugExists = static function (string $slug) use ($projects, $qa_questions): bool {
    if (safeHasTable('posts')) {
        return Post::where('slug', $slug)->exists();
    }
    return in_array($slug, collect($projects)->pluck('slug')->merge(collect($qa_questions)->pluck('slug'))->all(), true);
};

$useDbFeed = safeHasTable('posts') && Post::query()->exists();

$feed_tags = [];
$demoTagEntries = [];
if ($useDbFeed) {
    $feed_tags = FeedService::buildFeedTags(15);
}

if (empty($feed_tags)) {
    $tagBuckets = [];
    foreach ($projects as $project) {
        foreach (($project['tags'] ?? []) as $tag) {
            $label = trim((string) $tag);
            if ($label === '') {
                continue;
            }
            $key = Str::slug($label);
            if ($key === '') {
                continue;
            }
            if (!isset($tagBuckets[$key])) {
                $tagBuckets[$key] = ['label' => $label, 'slug' => $key, 'count' => 0];
            }
            $tagBuckets[$key]['count'] += 1;
        }
    }
    foreach ($qa_questions as $question) {
        foreach (($question['tags'] ?? []) as $tag) {
            $label = trim((string) $tag);
            if ($label === '') {
                continue;
            }
            $key = Str::slug($label);
            if ($key === '') {
                continue;
            }
            if (!isset($tagBuckets[$key])) {
                $tagBuckets[$key] = ['label' => $label, 'slug' => $key, 'count' => 0];
            }
            $tagBuckets[$key]['count'] += 1;
        }
    }

    $tagEntries = array_values($tagBuckets);
    usort($tagEntries, static function (array $a, array $b): int {
        $countCompare = ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
        if ($countCompare !== 0) {
            return $countCompare;
        }
        return strcmp($a['label'] ?? '', $b['label'] ?? '');
    });

    $demoTagEntries = $tagEntries;
    $feed_tags = array_values(array_slice($tagEntries, 0, 15));
}

$qa_threads = [];
if ($useDbFeed) {
    $qa_threads = FeedService::buildQaThreads(12);
}

if (empty($qa_threads)) {
    $qa_threads = array_map(static function ($question) {
        $slug = $question['slug'];
        $replies = count($question['answers'] ?? []);
        $score = (int) ($question['score'] ?? 0);
        $deltaValue = '+' . (int) $score;
        if (!str_starts_with($deltaValue, '+')) {
            $deltaValue = '+' . ltrim($deltaValue, '+');
        }
        $minutes = $question['published_minutes'] ?? 0;
        $timeLabel = $question['time'] ?? '';
        if (!$timeLabel && $minutes) {
            $timeLabel = now()->subMinutes($minutes)->diffForHumans();
        }
        return [
            'slug' => $slug,
            'title' => $question['title'],
            'time' => $timeLabel,
            'minutes' => $minutes,
            'replies' => $replies,
            'delta' => $deltaValue,
        ];
    }, $qa_questions);
}

$projectMap = collect($projects)->keyBy('slug');

$feed_page_size = 10;
$feed_projects = [];
$feed_questions = [];
$feed_items_page = [];
$feed_projects_total = 0;
$feed_questions_total = 0;
$feed_projects_offset = 0;
$feed_questions_offset = 0;

$sortFeedItems = static function (array $a, array $b): int {
    return ($a['published_minutes'] ?? 0) <=> ($b['published_minutes'] ?? 0);
};
$sortFeedByScore = static function (array $a, array $b): int {
    $scoreA = (int) ($a['data']['score'] ?? 0);
    $scoreB = (int) ($b['data']['score'] ?? 0);
    if ($scoreA !== $scoreB) {
        return $scoreB <=> $scoreA;
    }
    return ($a['published_minutes'] ?? 0) <=> ($b['published_minutes'] ?? 0);
};
$normalizeFeedFilter = static function (?string $filter): string {
    $value = strtolower((string) $filter);
    return in_array($value, ['best', 'fresh', 'reading'], true) ? $value : 'all';
};
$applyFeedFilter = static function (array $items, string $filter) use ($sortFeedByScore): array {
    if ($filter === 'fresh') {
        return array_values(array_filter($items, static function (array $item): bool {
            return (int) ($item['published_minutes'] ?? 0) <= 180;
        }));
    }
    if ($filter === 'reading') {
        return array_values(array_filter($items, static function (array $item): bool {
            return (int) ($item['data']['read_time_minutes'] ?? 0) >= 8;
        }));
    }
    if ($filter === 'best') {
        $sorted = $items;
        usort($sorted, $sortFeedByScore);
        return array_values($sorted);
    }
    return $items;
};

if ($useDbFeed) {
    $feed_items_page = [];
    $feed_projects_total = 0;
    $feed_questions_total = 0;
    $feed_projects_offset = 0;
    $feed_questions_offset = 0;
} else {
    $qaThreadMap = collect($qa_threads)->keyBy('slug');
    foreach ($projects as $project) {
        $feed_projects[] = [
            'type' => 'project',
            'data' => $project,
            'published_minutes' => (int) ($project['published_minutes'] ?? 0),
        ];
    }
    foreach ($qa_questions as $question) {
        $slug = $question['slug'] ?? '';
        $thread = $slug ? $qaThreadMap->get($slug) : null;
        $replies = $thread['replies'] ?? count($question['answers'] ?? []);
        $score = (int) ($question['score'] ?? 0);
        $question['replies'] = $replies;
        $question['score'] = $score;
        $feed_questions[] = [
            'type' => 'question',
            'data' => $question,
            'published_minutes' => (int) ($question['published_minutes'] ?? 0),
        ];
    }
    usort($feed_projects, $sortFeedItems);
    usort($feed_questions, $sortFeedItems);

    $feed_projects_total = count($feed_projects);
    $feed_questions_total = count($feed_questions);
    $feed_projects_page = array_slice($feed_projects, 0, $feed_page_size);
    $feed_questions_page = array_slice($feed_questions, 0, $feed_page_size);
    $feed_projects_offset = count($feed_projects_page);
    $feed_questions_offset = count($feed_questions_page);
    $feed_items_page = array_merge($feed_projects_page, $feed_questions_page);
    usort($feed_items_page, $sortFeedItems);
}

$top_projects = Cache::remember('feed.top_projects.v1', now()->addMinutes(10), function () use ($projectMap) {
    if (safeHasTable('posts') && safeHasTable('post_upvotes')) {
        $rowsQuery = DB::table('posts')
            ->leftJoin('post_upvotes', 'posts.id', '=', 'post_upvotes.post_id')
            ->where('posts.type', 'post')
            ->groupBy('posts.id', 'posts.slug', 'posts.title')
            ->select('posts.slug', 'posts.title', DB::raw('count(post_upvotes.id) as score'))
            ->orderByDesc('score')
            ->orderBy('posts.title')
            ->limit(4);
        app(VisibilityService::class)->applyToQuery($rowsQuery, 'posts', null);
        $rows = $rowsQuery->get();

        $entries = [];
        foreach ($rows as $row) {
            $entries[] = [
                'slug' => $row->slug,
                'title' => $row->title,
                'score' => (int) ($row->score ?? 0),
            ];
        }

        if (!empty($entries)) {
            return $entries;
        }
    }

    return $projectMap
        ->values()
        ->map(static fn (array $project) => [
            'slug' => $project['slug'],
            'title' => $project['title'],
            'score' => (int) ($project['score'] ?? 0),
        ])
        ->sortByDesc('score')
        ->take(4)
        ->values()
        ->all();
});

$reading_now = Cache::remember('feed.reading_now.v2', now()->addMinutes(2), function () use ($projectMap) {
    if (!safeHasTable('reading_activity') && !safeHasTable('reading_progress')) {
        return [];
    }

    $windowMinutes = 10;
    if (safeHasTable('reading_activity')) {
        $rows = DB::table('reading_activity')
            ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
            ->groupBy('post_id')
            ->select('post_id', DB::raw('count(distinct ip_hash) as readers'), DB::raw('max(updated_at) as last_read'))
            ->orderByDesc('readers')
            ->orderByDesc('last_read')
            ->limit(3)
            ->get();
    } else {
        $rows = DB::table('reading_progress')
            ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
            ->groupBy('post_id')
            ->select('post_id', DB::raw('count(*) as readers'), DB::raw('max(updated_at) as last_read'))
            ->orderByDesc('readers')
            ->orderByDesc('last_read')
            ->limit(3)
            ->get();
    }

    $postIds = $rows
        ->pluck('post_id')
        ->filter(static fn ($id) => is_numeric($id))
        ->map(static fn ($id) => (int) $id)
        ->unique()
        ->values()
        ->all();

    $postSlugs = $rows
        ->pluck('post_id')
        ->filter(static fn ($id) => is_string($id) && !is_numeric($id))
        ->map(static fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->all();

    $postMapById = collect();
    $postMapBySlug = collect();
    if (safeHasTable('posts')) {
        if (!empty($postIds)) {
            $postQuery = Post::whereIn('id', $postIds);
            app(VisibilityService::class)->applyToQuery($postQuery, 'posts', null);
            $postMapById = $postQuery->get(['id', 'slug', 'title'])->keyBy('id');
        }
        if (!empty($postSlugs)) {
            $postQuery = Post::whereIn('slug', $postSlugs);
            app(VisibilityService::class)->applyToQuery($postQuery, 'posts', null);
            $postMapBySlug = $postQuery->get(['id', 'slug', 'title'])->keyBy('slug');
        }
    }

    $entries = [];
    foreach ($rows as $row) {
        $postId = $row->post_id ?? null;
        if ($postId === null) {
            continue;
        }
        $slug = '';
        $title = '';
        if (is_numeric($postId)) {
            $post = $postMapById->get((int) $postId);
            $slug = $post?->slug ?? '';
            $title = $post?->title ?? '';
        } else {
            $post = $postMapBySlug->get((string) $postId);
            if ($post) {
                $slug = $post->slug ?? '';
                $title = $post->title ?? '';
            } else {
                $project = $projectMap->get((string) $postId);
                $slug = $project['slug'] ?? '';
                $title = $project['title'] ?? '';
            }
        }
        if ($slug === '' || $title === '') {
            continue;
        }
        $entries[] = [
            'slug' => $slug,
            'title' => $title,
            'readers' => (int) ($row->readers ?? 0),
            'last_read' => $row->last_read,
        ];
    }

    return $entries;
});

$searchIndex = Cache::remember('search.index.v1', now()->addMinutes(5), function () use ($useDbFeed, $projects, $qa_questions, $demoTagEntries) {
    $items = [];

    if ($useDbFeed) {
        $postsQuery = Post::with('user:id,name,slug')
            ->orderByDesc('created_at');
        app(VisibilityService::class)->applyToQuery($postsQuery, 'posts', null);
        $posts = $postsQuery
            ->limit(200)
            ->get(['id', 'slug', 'title', 'subtitle', 'type', 'user_id', 'tags']);

        foreach ($posts as $post) {
            $type = $post->type === 'question' ? 'question' : 'post';
            $items[] = [
                'type' => $type,
                'title' => $post->title,
                'subtitle' => $post->subtitle,
                'url' => $type === 'question'
                    ? url('/questions/' . $post->slug)
                    : url('/projects/' . $post->slug),
                'slug' => $post->slug,
                'author' => $post->user?->name ?? null,
                'keywords' => is_array($post->tags) ? implode(' ', $post->tags) : null,
            ];
        }
    } else {
        foreach ($projects as $project) {
            $items[] = [
                'type' => 'post',
                'title' => $project['title'] ?? '',
                'subtitle' => $project['subtitle'] ?? $project['context'] ?? null,
                'url' => url('/projects/' . $project['slug']),
                'slug' => $project['slug'],
                'author' => $project['author']['name'] ?? null,
                'keywords' => !empty($project['tags']) ? implode(' ', $project['tags']) : null,
            ];
        }
        foreach ($qa_questions as $question) {
            $items[] = [
                'type' => 'question',
                'title' => $question['title'] ?? '',
                'subtitle' => $question['body'] ?? null,
                'url' => url('/questions/' . $question['slug']),
                'slug' => $question['slug'],
                'author' => $question['author']['name'] ?? null,
                'keywords' => !empty($question['tags']) ? implode(' ', $question['tags']) : null,
            ];
        }
    }

    $tagEntries = $demoTagEntries;
    if (empty($tagEntries) && $useDbFeed) {
        $tagEntries = FeedService::buildFeedTags(200, 500);
    }
    $tagEntries = array_slice($tagEntries, 0, 200);
    foreach ($tagEntries as $entry) {
        $label = trim((string) ($entry['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $slug = $entry['slug'] ?? Str::slug($label);
        if ($slug === '') {
            continue;
        }
        $count = (int) ($entry['count'] ?? 0);
        $items[] = [
            'type' => 'tag',
            'title' => '#' . $label,
            'subtitle' => __('ui.search_tag_posts', ['count' => $count]),
            'url' => url('/?tags=' . $slug),
            'slug' => $slug,
            'author' => null,
            'keywords' => implode(' ', array_filter([$label, '#' . $label, $slug])),
        ];
    }

    if (safeHasTable('users') && User::query()->exists()) {
        $users = User::query()->orderBy('name')->limit(200)->get(['id', 'name', 'slug', 'role', 'bio']);
        foreach ($users as $user) {
            $slug = $user->slug ?? Str::slug($user->name ?? '');
            if ($slug === '') {
                continue;
            }
            $items[] = [
                'type' => 'user',
                'title' => $user->name ?? '',
                'subtitle' => $user->role ?? null,
                'url' => url('/profile/' . $slug),
                'slug' => $slug,
                'author' => null,
                'keywords' => $user->bio ?? null,
            ];
        }
    } else {
        $demoUsers = [];
        foreach ([$projects, $qa_questions] as $collection) {
            foreach ($collection as $entry) {
                $author = $entry['author']['name'] ?? null;
                if (!$author) {
                    continue;
                }
                $slug = $entry['author']['slug'] ?? Str::slug($author);
                if ($slug === '') {
                    continue;
                }
                $role = $entry['author']['role'] ?? null;
                $demoUsers[$slug] = [
                    'type' => 'user',
                    'title' => $author,
                    'subtitle' => $role,
                    'url' => url('/profile/' . $slug),
                    'slug' => $slug,
                    'author' => null,
                    'keywords' => null,
                ];
            }
        }
        $items = array_merge($items, array_values($demoUsers));
    }

    return $items;
});

$topbarPromo = app(TopbarPromoService::class)->pickPromo();

view()->composer(['layouts.app', 'layouts.support'], function ($view) {
    $payload = app(\App\Services\NotificationService::class)
        ->buildPayload((array) config('notifications.seed', []));
    $view->with([
        'unreadNotifications' => $payload['unreadPreview'],
        'unreadCount' => $payload['unreadCount'],
    ]);
});

view()->share([
    'searchIndex' => $searchIndex,
    'topbar_promo' => $topbarPromo,
]);

