<?php

namespace Tests\Feature;

use App\Models\CollaborationApplication;
use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\ProjectMember;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_accounts_do_not_use_the_factory_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, User::query()->count());
        User::query()->each(function (User $user): void {
            $this->assertFalse(Hash::check('password', $user->password));
        });
    }

    public function test_collaboration_seeding_contains_open_and_filled_scenarios(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(7, CollaborationRequest::query()->where('status', 'open')->count());
        $this->assertSame(1, CollaborationRequest::query()->where('status', 'filled')->count());
        $this->assertGreaterThanOrEqual(7, CollaborationApplication::query()->where('status', 'pending')->count());
        $this->assertSame(1, CollaborationApplication::query()->where('status', 'accepted')->count());
        $this->assertGreaterThanOrEqual(5, ProjectMember::query()->where('status', 'active')->count());
        $this->assertGreaterThanOrEqual(25, User::query()->count());
        $this->assertGreaterThanOrEqual(60, Post::query()->where('type', 'post')->count());
        $this->assertGreaterThanOrEqual(25, Post::query()->where('type', 'question')->count());
        $this->assertGreaterThanOrEqual(300, PostComment::query()->count());
        $this->assertGreaterThanOrEqual(20, PostReview::query()->count());
        $this->assertSame(Post::query()->count(), Post::query()->distinct()->count('title'));
        $this->assertSame(
            'Calm dashboard for student teams',
            Post::query()->where('slug', 'collab-quiet-dashboard')->value('title'),
        );
    }
}
