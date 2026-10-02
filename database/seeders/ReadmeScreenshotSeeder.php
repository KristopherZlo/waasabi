<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReadmeScreenshotSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(WaasabiDemoSeeder::class);

        $posts = [
            ['night-bus-photo-essay', 'vera-kim', 'Night bus, after dark',
                'Thirty evenings of rain, reflections, and the last route home.', 'night-bus'],
            ['procedural-moss-game', 'emil-saar', 'Pocket Garden',
                'A ten-minute exploration game. One courtyard, a little moss, and room to wander.', 'pocket-garden'],
            ['open-source-looper-pedal', 'maja-lind', 'Loop: a repairable looper',
                'An open-source music pedal with common parts and controls you can feel.', 'looper'],
        ];

        $users = User::query()->where('is_banned', false)->get();
        foreach ($posts as $index => [$slug, $authorSlug, $title, $subtitle, $artwork]) {
            $author = $users->firstWhere('slug', $authorSlug);
            $post = Post::query()->where('slug', $slug)->firstOrFail();
            $cover = '/images/readme/'.$artwork.'.jpg';
            $post->update([
                'user_id' => $author->id,
                'is_project' => $artwork === 'looper',
                'title' => $title,
                'subtitle' => $subtitle,
                'cover_url' => $cover,
                'album_urls' => [],
                'feedback_mode' => 'feedback',
                'activity_at' => now()->subMinutes($index),
            ]);
            $post->forceFill(['created_at' => $post->published_at])->saveQuietly();
            $post->upvoters()->sync($users->where('id', '!=', $author->id)->take(24 - $index * 3)->pluck('id'));
            $author->update([
                'featured_post_id' => $post->id,
                'banner_url' => $cover,
                'headline' => ['Documentary photographer', 'Independent game developer', 'Sound designer and hardware maker'][$index],
            ]);
            $author->showcaseProjects()->sync([$post->id => ['position' => 0]]);

            if ($post->is_project) {
                ProjectUpdate::updateOrCreate(['post_id' => $post->id, 'title' => 'First playable prototype'], [
                    'user_id' => $author->id,
                    'body' => "![The assembled looper prototype]({$cover})\n\nThe first enclosure is assembled. Four pads control record, play, overdub, and undo.\n\nNext: test the controls with musicians and publish the enclosure files.",
                    'is_hidden' => false,
                ]);
            }
        }
    }
}
