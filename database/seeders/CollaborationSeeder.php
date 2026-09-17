<?php

namespace Database\Seeders;

use App\Models\CollaborationApplication;
use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class CollaborationSeeder extends Seeder
{
    public function run(): void
    {
        $projectContexts = [
            'collab-quiet-dashboard' => [
                'title' => 'Calm dashboard for student teams',
                'subtitle' => 'A weekly progress workspace designed to keep small teams focused without notification noise.',
                'body_markdown' => "## Current build\nThe core weekly workflow and data model are working. Teams can record progress, blockers, and the next concrete action.\n\n## Next milestone\nWe are preparing the interface system and testing the dashboard with three student groups.",
            ],
            'collab-sensor-kit' => [
                'title' => 'Battery-powered field sensor kit',
                'subtitle' => 'A compact environmental sensor node built for long field tests and simple data collection.',
                'body_markdown' => "## Current build\nThe board, enclosure, and sensor stack are working on the bench. Power measurements and radio tests are documented.\n\n## Next milestone\nWe are reducing sleep current and making the firmware dependable enough for a two-week outdoor test.",
            ],
            'collab-community-docs' => [
                'title' => 'Open handbook for community builds',
                'subtitle' => 'Practical guides that turn working prototypes into repeatable community projects.',
                'body_markdown' => "## Current build\nWe have field notes, diagrams, and photographs from several completed builds. The source material is accurate but inconsistent.\n\n## Next milestone\nWe are publishing the first structured guide and defining a reusable documentation format.",
            ],
        ];
        foreach ($projectContexts as $slug => $context) {
            Post::query()->where('slug', $slug)->update($context + ['body_html' => null]);
        }

        $posts = Post::query()
            ->whereIn('slug', [
                'collab-quiet-dashboard',
                'collab-sensor-kit',
                'collab-community-docs',
                'weekend-gesture-board',
                'field-notes',
            ])
            ->get()
            ->keyBy('slug');
        $users = User::query()
            ->whereIn('email', [
                'mila@thehub.test',
                'ilya@thehub.test',
                'katya@thehub.test',
                'sveta@thehub.test',
                'timur@thehub.test',
            ])
            ->get()
            ->keyBy('email');

        $quietDashboard = CollaborationRequest::updateOrCreate(
            ['post_id' => $posts->get('collab-quiet-dashboard')?->id],
            [
                'user_id' => $users->get('mila@thehub.test')?->id,
                'title' => 'Design a calm team dashboard together',
                'role' => 'designer',
                'skills' => ['interface design', 'design systems', 'prototyping'],
                'availability' => 'part-time',
                'format' => 'remote',
                'summary' => 'Help shape a restrained visual system, clarify the weekly progress view, and prepare reusable dashboard components for implementation.',
                'status' => 'open',
                'expires_at' => now()->addDays(45),
                'closed_at' => null,
            ],
        );

        $sensorKit = CollaborationRequest::updateOrCreate(
            ['post_id' => $posts->get('collab-sensor-kit')?->id],
            [
                'user_id' => $users->get('ilya@thehub.test')?->id,
                'title' => 'Build low-power sensor firmware together',
                'role' => 'developer',
                'skills' => ['C', 'firmware', 'low-power hardware'],
                'availability' => 'part-time',
                'format' => 'async',
                'summary' => 'Take the working hardware prototype through sleep-cycle tuning, dependable sensor polling, and a small documented radio packet format.',
                'status' => 'open',
                'expires_at' => now()->addDays(60),
                'closed_at' => null,
            ],
        );

        $communityDocs = CollaborationRequest::updateOrCreate(
            ['post_id' => $posts->get('collab-community-docs')?->id],
            [
                'user_id' => $users->get('katya@thehub.test')?->id,
                'title' => 'Turn community build notes into guides together',
                'role' => 'writer',
                'skills' => ['technical writing', 'editing', 'documentation'],
                'availability' => 'regular',
                'format' => 'remote',
                'summary' => 'Turn field notes into a welcoming guide with a clear structure, concise explanations, and repeatable documentation conventions.',
                'status' => 'filled',
                'expires_at' => now()->addDays(30),
                'closed_at' => now()->subDays(2),
            ],
        );

        CollaborationApplication::updateOrCreate(
            [
                'collaboration_request_id' => $quietDashboard->id,
                'user_id' => $users->get('sveta@thehub.test')?->id,
            ],
            [
                'applicant_post_id' => $posts->get('weekend-gesture-board')?->id,
                'message' => 'I can map the weekly workflow, build the core interface states, and hand over a compact component spec backed by my gesture-panel case study.',
                'status' => 'pending',
                'decided_at' => null,
            ],
        );

        CollaborationApplication::updateOrCreate(
            [
                'collaboration_request_id' => $sensorKit->id,
                'user_id' => $users->get('timur@thehub.test')?->id,
            ],
            [
                'applicant_post_id' => null,
                'message' => 'I have been testing mixed-signal sensor boards and can take on sleep profiling, debounce logic, and repeatable bench measurements.',
                'status' => 'pending',
                'decided_at' => null,
            ],
        );

        CollaborationApplication::updateOrCreate(
            [
                'collaboration_request_id' => $communityDocs->id,
                'user_id' => $users->get('mila@thehub.test')?->id,
            ],
            [
                'applicant_post_id' => $posts->get('field-notes')?->id,
                'message' => 'My field-notes project uses the same calm, progressive-disclosure approach. I can turn the raw build log into a coherent first guide.',
                'status' => 'accepted',
                'decided_at' => now()->subDays(2),
            ],
        );

        ProjectMember::updateOrCreate(
            [
                'post_id' => $posts->get('collab-community-docs')?->id,
                'user_id' => $users->get('mila@thehub.test')?->id,
            ],
            [
                'invited_by' => $users->get('katya@thehub.test')?->id,
                'role' => 'writer',
                'status' => 'active',
                'can_edit' => true,
                'accepted_at' => now()->subDays(2),
            ],
        );

        $extraOpenings = [
            'tactile-transit-map' => [
                'title' => 'Test tactile transit maps together',
                'role' => 'researcher',
                'skills' => ['accessibility research', 'interviews', 'public transit'],
                'availability' => 'one-time',
                'format' => 'on-site',
                'summary' => 'Plan and run two focused test sessions with blind and low-vision riders, then turn observations into specific map revisions.',
                'status' => 'open',
            ],
            'open-source-looper-pedal' => [
                'title' => 'Tune an open-source looper pedal together',
                'role' => 'sound-designer',
                'skills' => ['guitar effects', 'gain staging', 'field testing'],
                'availability' => 'part-time',
                'format' => 'hybrid',
                'summary' => 'Test the current pedal with several instruments, document noise and gain problems, and help define practical factory presets.',
                'status' => 'open',
            ],
            'river-sound-archive' => [
                'title' => 'Document a seasonal river sound archive together',
                'role' => 'photographer',
                'skills' => ['documentary photography', 'field work', 'metadata'],
                'availability' => 'regular',
                'format' => 'on-site',
                'summary' => 'Photograph four fixed recording locations across changing weather and build a consistent visual record for the listening map.',
                'status' => 'open',
            ],
            'caption-first-video-editor' => [
                'title' => 'Build a caption-first video editor together',
                'role' => 'developer',
                'skills' => ['TypeScript', 'media timelines', 'accessibility'],
                'availability' => 'part-time',
                'format' => 'remote',
                'summary' => 'Implement transcript editing and timeline synchronization for a tested prototype, with keyboard navigation treated as a core requirement.',
                'status' => 'open',
            ],
            'low-bandwidth-learning-kit' => [
                'title' => 'Adapt a course for offline learning together',
                'role' => 'designer',
                'skills' => ['instructional design', 'low-bandwidth content', 'usability testing'],
                'availability' => 'regular',
                'format' => 'async',
                'summary' => 'Turn one existing workshop into a small offline module with clear checkpoints, printable fallbacks, and a realistic teacher handoff.',
                'status' => 'open',
            ],
            'community-radio-jingles' => [
                'title' => 'Record an open community-radio sound kit together',
                'role' => 'vocalist',
                'skills' => ['voice recording', 'radio', 'multilingual performance'],
                'availability' => 'one-time',
                'format' => 'on-site',
                'summary' => 'Record a compact set of station names, transitions, and neutral vocal textures that volunteers can remix under an open license.',
                'status' => 'closed',
            ],
            'portable-exhibition-wall' => [
                'title' => 'Illustrate assembly and safety instructions together',
                'role' => 'illustrator',
                'skills' => ['technical illustration', 'print layout', 'assembly guides'],
                'availability' => 'one-time',
                'format' => 'remote',
                'summary' => 'Create a concise illustrated guide covering panel assembly, adjustable feet, safe loads, storage, and common repair steps.',
                'status' => 'closed',
            ],
        ];

        $extraPosts = Post::query()->whereIn('slug', array_keys($extraOpenings))->get()->keyBy('slug');
        $applicants = User::query()->orderBy('id')->get();
        foreach ($extraOpenings as $slug => $opening) {
            $post = $extraPosts->get($slug);
            if (! $post) {
                continue;
            }
            $status = $opening['status'];
            $request = CollaborationRequest::updateOrCreate(
                ['post_id' => $post->id],
                [
                    'user_id' => $post->user_id,
                    'title' => $opening['title'],
                    'role' => $opening['role'],
                    'skills' => $opening['skills'],
                    'availability' => $opening['availability'],
                    'format' => $opening['format'],
                    'summary' => $opening['summary'],
                    'status' => $status,
                    'expires_at' => now()->addDays(30),
                    'closed_at' => $status === 'closed' ? now()->subDays(3) : null,
                ],
            );
            if ($status !== 'open') {
                continue;
            }
            $applicant = $applicants->first(fn (User $user) => $user->id !== $post->user_id && $user->id % 5 === $request->id % 5)
                ?? $applicants->first(fn (User $user) => $user->id !== $post->user_id);
            CollaborationApplication::updateOrCreate(
                ['collaboration_request_id' => $request->id, 'user_id' => $applicant->id],
                [
                    'applicant_post_id' => null,
                    'message' => 'The scope matches my recent work. I can share a small first deliverable this week and document decisions so the project remains easy to hand over.',
                    'status' => 'pending',
                    'decided_at' => null,
                ],
            );
        }
    }
}
