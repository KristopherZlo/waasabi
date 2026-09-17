<?php

namespace Database\Seeders;

use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\User;
use App\Services\ScribbleAvatar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WaasabiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Sample accounts are only available locally.');
        }
        File::ensureDirectoryExists(public_path('images/waasabi'));
        $makers = [
            ['name' => 'Alex', 'slug' => 'alex-sample', 'skills' => 'Generative art, drawing, creative coding', 'bio' => 'I like turning small experiments into things people can play with.'],
            ['name' => 'Mika', 'slug' => 'mika-sample', 'skills' => '3D, Blender, low-poly, games', 'bio' => 'Happy to help with a small prop or a scene for an evening.'],
            ['name' => 'Robin', 'slug' => 'robin-sample', 'skills' => 'Writing, illustration, sound', 'bio' => 'Making a tiny illustrated world, one page at a time.'],
        ];
        $users = collect($makers)->map(function ($data) {
            $user = User::firstOrCreate(['email' => $data['slug'].'@example.test'], $data + [
                'password' => Hash::make(Str::random(40)), 'email_verified_at' => now(), 'role' => 'user', 'open_to_help' => true,
            ]);
            $path = 'images/waasabi/'.$user->slug.'.svg';
            File::put(public_path($path), ScribbleAvatar::createSvgFromName($user->name));
            $user->update(['avatar' => '/'.$path]);

            return $user;
        });
        $works = [
            ['A line that finds its own way', 'An experiment in drawing with very simple rules.', 'visual-art', 'sharing', 0, 'line-study'],
            ['Small worlds, drawn after work', 'A sketchbook for places that do not exist yet.', 'illustration', 'feedback', 2, 'small-worlds'],
            ['A pocket-sized game about getting lost', 'Looking for a friend to make one small object together.', 'game', 'help', 1, 'pocket-game'],
            ['Notes from an unfinished thing', 'A place for experiments before they become a finished project.', 'writing', 'sharing', 2, 'unfinished-notes'],
        ];
        foreach ($works as [$title, $subtitle, $category, $feedback, $index, $slug]) {
            $imagePath = 'images/waasabi/'.$slug.'.svg';
            File::put(public_path($imagePath), ScribbleAvatar::createSvgFromName($slug));
            $post = Post::firstOrCreate(['slug' => $slug], [
                'user_id' => $users[$index]->id, 'type' => 'post', 'title' => $title, 'subtitle' => $subtitle,
                'category' => $category, 'media_type' => 'mixed', 'license' => 'all-rights-reserved', 'visibility' => 'public',
                'moderation_status' => 'approved', 'is_hidden' => false, 'feedback_mode' => $feedback,
                'status' => 'in_progress', 'published_at' => now(), 'cover_url' => '/'.$imagePath,
                'tags' => [$category], 'read_time_minutes' => 1,
                'body_markdown' => "## Why I started\n\nI wanted a place to share something while it is still taking shape. The image above comes from the original Hub scribble generator.\n\n## What comes next\n\nA small experiment, a conversation, and another update.\n\n## What I would love to hear\n\nWhat does this make you think of?",
            ]);
            $post->updates()->firstOrCreate(['title' => 'The first small step'], [
                'user_id' => $users[$index]->id,
                'body' => "## An early experiment\n\nStarting with one line and seeing where it leads.\n\n![A drawing made with the scribble generator](/$imagePath)",
            ]);
            if ($feedback === 'help') {
                CollaborationRequest::firstOrCreate(['title' => 'Make one low-poly prop for a tiny game'], [
                    'post_id' => $post->id, 'user_id' => $users[$index]->id, 'role' => '3d-artist',
                    'skills' => ['Blender', 'low-poly'], 'availability' => 'one-time', 'format' => 'remote',
                    'summary' => 'A small lamp for a game scene. One evening, just for fun. We can decide together whether to keep going.',
                    'status' => 'open', 'expires_at' => now()->addDays(30),
                ]);
            }
        }
        $users[0]->update(['featured_post_id' => Post::where('slug', 'line-study')->value('id')]);
    }
}
