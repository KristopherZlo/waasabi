<?php

namespace Database\Seeders;

use App\Models\CollaborationApplication;
use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\ProfileWallPost;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommunityDetailsSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->orderBy('id')->get();
        $posts = Post::query()->with('user')->orderBy('id')->get();
        if ($users->isEmpty() || $posts->isEmpty()) {
            return;
        }

        $this->seedProfiles($users);
        $this->seedPostDetails($posts);
        $this->seedShowcases($users);
        $this->seedWalls($users);
        $this->seedProjectFollows($users);
        $this->seedCollaborationThreads($users);
        $this->seedNotifications($users, $posts);
    }

    private function seedProfiles(Collection $users): void
    {
        $skills = [
            'dasha-n' => 'Hardware prototyping, field testing, documentation',
            'ilya-m' => 'Embedded systems, PCB design, power electronics',
            'sveta-l' => 'Interaction design, prototyping, user research',
            'timur-k' => 'Sensors, Arduino, test automation',
            'nikita-b' => 'Product critique, research, facilitation',
            'mila-t' => 'Editorial design, information architecture, UX writing',
            'katya-f' => 'Project planning, technical writing, community programs',
            'artem-volkov' => 'Woodworking, furniture, repairable construction',
            'lena-ortiz' => 'Data visualization, maps, public-interest design',
            'noora-laine' => 'Ceramics, glaze testing, studio systems',
            'emil-saar' => 'Game design, Unity, accessible controls',
            'vera-kim' => 'Documentary photography, archives, oral history',
            'anton-reyes' => 'CAD, 3D printing, mechanical prototyping',
            'sofia-berg' => 'Teaching, workshop design, open education',
            'leo-martins' => 'Open source, offline-first software, web accessibility',
            'aino-kallio' => 'Textiles, embroidery, data art',
            'mika-chen' => 'Robotics, mechanisms, safety testing',
            'robin-adeyemi' => 'Climate research, community data, field surveys',
            'maja-lind' => 'Sound design, field recording, audio editing',
            'samir-patel' => 'Volunteer coordination, onboarding, documentation',
        ];
        $readmes = [
            'dasha-n' => "## What I make\n\nI build small hardware tools and test them outside the lab. I publish measurements, failed revisions, and repair notes.\n\n**Open to:** short field tests, enclosure reviews, and sensor projects.",
            'mila-t' => "## Current focus\n\nI turn complex team work into calm reading and writing flows. I can help with structure, interface copy, and documentation.",
            'artem-volkov' => "## Workshop notes\n\nMy projects use common tools, replaceable parts, and clear assembly steps. I share jigs when they save another maker time.",
            'lena-ortiz' => "## Public data, clearly shown\n\nI design maps and charts that explain uncertainty. I am interested in climate, transport, and neighborhood research.",
            'noora-laine' => "## Studio practice\n\nI document glaze tests, firing changes, and material waste. Good records make experiments easier to repeat and share.",
            'leo-martins' => "## Software for unreliable connections\n\nI maintain small open tools that work offline, respect old devices, and remain understandable to new contributors.",
        ];
        $avatars = [
            '/images/waasabi/alex-sample.svg',
            '/images/waasabi/mika-sample.svg',
            '/images/waasabi/robin-sample.svg',
            '/images/waasabi/line-study.svg',
            '/images/waasabi/small-worlds.svg',
            '/images/waasabi/pocket-game.svg',
            '/images/waasabi/unfinished-notes.svg',
        ];

        foreach ($users as $index => $user) {
            $user->update([
                'avatar' => $avatars[$index % count($avatars)],
                'banner_url' => '/images/cover-'.(($index % 4) + 1).'.svg',
                'skills' => $skills[$user->slug] ?? 'Creative practice, feedback, collaboration',
                'open_to_help' => $index % 4 !== 3,
                'portfolio_url' => $index % 3 === 0 ? 'https://example.com/portfolio/'.$user->slug : null,
                'profile_readme' => $readmes[$user->slug] ?? "## About my work\n\nI share work in progress, practical notes, and the decisions behind each project.",
                'wall_mode' => $index % 5 === 4 ? 'owner' : 'everyone',
            ]);
        }
    }

    private function seedPostDetails(Collection $posts): void
    {
        $covers = ['/images/cover-1.svg', '/images/cover-2.svg', '/images/cover-3.svg', '/images/cover-4.svg'];

        foreach ($posts as $index => $post) {
            $publishedAt = $post->published_at ?? now()->subHours(6 + (($index * 17) % (24 * 45)));
            $details = [
                'is_project' => $post->type === 'question' ? false : $post->is_project,
                'feedback_mode' => $post->type === 'question' ? 'feedback' : ['sharing', 'feedback', 'help'][$index % 3],
                'category' => $post->category ?: 'other',
                'media_type' => $post->type === 'question' ? 'text' : ($post->media_type ?: 'mixed'),
                'license' => $post->license ?: 'all-rights-reserved',
                'visibility' => 'public',
                'moderation_status' => 'approved',
                'is_hidden' => false,
                'published_at' => $publishedAt,
                'activity_at' => $post->activity_at ?? $publishedAt->copy()->addHours($index % 9),
            ];
            if ($post->type !== 'question' && ! $post->cover_url) {
                $details['cover_url'] = $covers[$index % count($covers)];
            }
            if ($post->type !== 'question' && $index % 10 === 0) {
                $details['album_urls'] = [$covers[$index % 4], $covers[($index + 1) % 4], $covers[($index + 2) % 4]];
            }
            $post->update($details);
        }

        Post::query()->where('slug', 'tiny-museum-audio-guide')->update([
            'external_url' => 'https://example.com/tiny-museum-guide',
            'repository_url' => 'https://github.com/example/tiny-museum-guide',
        ]);
        Post::query()->where('slug', 'open-source-looper-pedal')->update([
            'repository_url' => 'https://github.com/example/open-looper',
        ]);
    }

    private function seedShowcases(Collection $users): void
    {
        foreach ($users->take(10) as $user) {
            $projects = Post::query()->where('user_id', $user->id)->where('type', 'post')->where('is_project', true)
                ->where('visibility', 'public')->orderByDesc('published_at')->take(3)->get();
            if ($projects->isEmpty()) {
                continue;
            }
            $showcase = $projects->values()->mapWithKeys(fn (Post $post, int $position) => [$post->id => ['position' => $position]])->all();
            $user->showcaseProjects()->sync($showcase);
            $user->update(['featured_post_id' => $projects->first()->id]);
        }
    }

    private function seedWalls(Collection $users): void
    {
        $notes = [
            'I posted the latest test notes today. The failed revision is included because it explains the final choice.',
            'This week I am keeping the scope small: one prototype, one real test, and one clear next step.',
            'I cleaned up the project journal and added the measurements people asked for in the discussion.',
            'I have two free evenings next week if someone needs a second pair of eyes on a small build.',
        ];
        $visitorNotes = [
            'Your build log helped us avoid the same dead end. Thank you for showing the rough version too.',
            'The handoff notes are excellent. I could reproduce the setup without asking for extra context.',
            'I would love to see the next field test. The current comparison is already useful.',
        ];

        foreach ($users->take(10)->values() as $index => $profile) {
            $visitor = $users[($index + 3) % $users->count()];
            foreach ([[$profile, $notes[$index % count($notes)]], [$visitor, $visitorNotes[$index % count($visitorNotes)]]] as $offset => [$author, $body]) {
                $wallPost = ProfileWallPost::updateOrCreate([
                    'profile_user_id' => $profile->id,
                    'user_id' => $author->id,
                    'body' => $body,
                ], ['is_hidden' => false, 'moderation_status' => 'approved']);
                $createdAt = now()->subHours(8 + $index * 7 + $offset * 2);
                $wallPost->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
            }
        }
    }

    private function seedProjectFollows(Collection $users): void
    {
        $projects = Post::query()->where('type', 'post')->where('is_project', true)->where('visibility', 'public')->get();
        foreach ($projects as $projectIndex => $project) {
            foreach ([2, 7, 11] as $offset) {
                $follower = $users[($projectIndex + $offset) % $users->count()];
                if ($follower->id === $project->user_id) {
                    continue;
                }
                DB::table('project_follows')->insertOrIgnore([
                    'post_id' => $project->id,
                    'user_id' => $follower->id,
                    'created_at' => now()->subDays(($projectIndex + $offset) % 18),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedCollaborationThreads(Collection $users): void
    {
        $requests = CollaborationRequest::query()->with(['user', 'post', 'applications.user'])->orderBy('id')->get();
        foreach ($requests as $requestIndex => $request) {
            $participants = $users->where('id', '!=', $request->user_id)->values();
            foreach ([
                'Is there a small first task that would help before someone commits to the full scope?',
                'What does a useful handoff look like for this project, and which tools are already in place?',
            ] as $commentIndex => $body) {
                $author = $participants[($requestIndex + $commentIndex * 3) % $participants->count()];
                CollaborationComment::updateOrCreate([
                    'collaboration_request_id' => $request->id,
                    'collaboration_application_id' => null,
                    'user_id' => $author->id,
                    'body' => $body,
                ]);
            }

            foreach ($request->applications as $applicationIndex => $application) {
                if (! in_array($application->status, ['pending', 'accepted'], true)) {
                    continue;
                }
                foreach ([
                    [$application->user_id, 'I can send a compact first draft this week. Which part should I test before our first call?'],
                    [$request->user_id, 'Start with the smallest working example. Share the result here and I will add the project context you need.'],
                ] as [$authorId, $body]) {
                    CollaborationComment::updateOrCreate([
                        'collaboration_request_id' => $request->id,
                        'collaboration_application_id' => $application->id,
                        'user_id' => $authorId,
                        'body' => $body,
                    ]);
                }
            }
        }

        $dasha = $users->firstWhere('slug', 'dasha-n');
        $opening = $requests->firstWhere('post.slug', 'tactile-transit-map');
        $project = Post::query()->where('slug', 'power-hub-night')->first();
        if ($dasha && $opening && $project && $opening->user_id !== $dasha->id) {
            $application = CollaborationApplication::updateOrCreate([
                'collaboration_request_id' => $opening->id,
                'user_id' => $dasha->id,
            ], [
                'applicant_post_id' => $project->id,
                'message' => 'I can help plan the physical test setup, document observations, and turn the first session into a practical revision list.',
                'status' => 'accepted',
                'decided_at' => now()->subDays(4),
            ]);
            ProjectMember::updateOrCreate(['post_id' => $opening->post_id, 'user_id' => $dasha->id], [
                'invited_by' => $opening->user_id,
                'role' => 'researcher',
                'status' => 'active',
                'can_edit' => false,
                'accepted_at' => now()->subDays(4),
            ]);
            foreach ([
                [$dasha->id, 'I can send a compact first draft this week. Which part should I test before our first call?'],
                [$opening->user_id, 'Start with the smallest working example. Share the result here and I will add the project context you need.'],
            ] as [$authorId, $body]) {
                CollaborationComment::updateOrCreate([
                    'collaboration_request_id' => $opening->id,
                    'collaboration_application_id' => $application->id,
                    'user_id' => $authorId,
                    'body' => $body,
                ]);
            }
            CollaborationComment::updateOrCreate([
                'collaboration_request_id' => $opening->id,
                'collaboration_application_id' => $application->id,
                'user_id' => $opening->user_id,
                'body' => 'Welcome aboard. I added the test script and the notes from our first rider interview.',
            ]);
        }
    }

    private function seedNotifications(Collection $users, Collection $posts): void
    {
        foreach ($users as $userIndex => $user) {
            foreach ([1, 5] as $offset) {
                $post = $posts[($userIndex * 3 + $offset) % $posts->count()];
                UserNotification::updateOrCreate([
                    'user_id' => $user->id,
                    'type' => $offset === 1 ? 'Comment' : 'Appreciation',
                    'text' => $offset === 1
                        ? "A new reply was added to {$post->title}."
                        : "Someone appreciated {$post->title}.",
                ], [
                    'link' => $post->type === 'question' ? route('questions.show', $post->slug) : route('project', $post->slug),
                    'read_at' => ($userIndex + $offset) % 3 === 0 ? now()->subHours($offset) : null,
                ]);
            }
        }
    }
}
