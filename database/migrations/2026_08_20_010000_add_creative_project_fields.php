<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('category', 40)->nullable()->after('type');
            $table->string('media_type', 30)->default('mixed')->after('category');
            $table->string('license', 40)->default('all-rights-reserved')->after('media_type');
            $table->string('external_url', 500)->nullable()->after('media_url');
            $table->string('repository_url', 500)->nullable()->after('external_url');
            $table->string('visibility', 20)->default('public')->after('status');
            $table->timestamp('published_at')->nullable()->after('visibility');
            $table->index(['type', 'visibility', 'moderation_status', 'created_at'], 'posts_feed_index');
            $table->index(['category', 'visibility', 'created_at'], 'posts_category_index');
        });

        DB::table('posts')->update(['published_at' => DB::raw('created_at')]);

        Schema::create('post_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('kind', 20)->default('file');
            $table->timestamps();
            $table->index(['post_id', 'kind']);
        });

        Schema::create('project_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->timestamps();
            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
        Schema::dropIfExists('post_attachments');

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex('posts_feed_index');
            $table->dropIndex('posts_category_index');
            $table->dropColumn([
                'category',
                'media_type',
                'license',
                'external_url',
                'repository_url',
                'visibility',
                'published_at',
            ]);
        });
    }
};
