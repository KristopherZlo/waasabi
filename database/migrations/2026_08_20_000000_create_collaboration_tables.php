<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('role', 60);
            $table->json('skills')->nullable();
            $table->string('availability', 30);
            $table->string('format', 30);
            $table->text('summary');
            $table->string('status', 20)->default('open');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['role', 'status']);
            $table->index(['post_id', 'status']);
        });

        Schema::create('collaboration_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collaboration_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('status', 20)->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['collaboration_request_id', 'user_id'], 'collab_apps_request_user_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 80)->default('contributor');
            $table->string('status', 20)->default('invited');
            $table->boolean('can_edit')->default(true);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        $now = now();
        DB::table('posts')
            ->where('type', 'post')
            ->where('tags', 'like', '%collaboration%')
            ->orderBy('id')
            ->each(function (object $post) use ($now): void {
                DB::table('collaboration_requests')->insert([
                    'post_id' => $post->id,
                    'user_id' => $post->user_id,
                    'title' => mb_substr((string) $post->title, 0, 120),
                    'role' => 'other',
                    'skills' => $post->tags,
                    'availability' => 'flexible',
                    'format' => 'remote',
                    'summary' => (string) ($post->subtitle ?: $post->body_markdown ?: $post->title),
                    'status' => 'open',
                    'created_at' => $post->created_at ?: $now,
                    'updated_at' => $post->updated_at ?: $now,
                ]);
            });

        if (Schema::hasColumn('posts', 'coauthor_user_ids')) {
            DB::table('posts')->whereNotNull('coauthor_user_ids')->orderBy('id')->each(function (object $post) use ($now): void {
                $ids = json_decode((string) $post->coauthor_user_ids, true);
                foreach (array_unique(array_map('intval', is_array($ids) ? $ids : [])) as $userId) {
                    if ($userId <= 0 || $userId === (int) $post->user_id) {
                        continue;
                    }
                    DB::table('project_members')->insertOrIgnore([
                        'post_id' => $post->id,
                        'user_id' => $userId,
                        'invited_by' => $post->user_id,
                        'role' => 'coauthor',
                        'status' => 'invited',
                        'can_edit' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('collaboration_applications');
        Schema::dropIfExists('collaboration_requests');
    }
};
