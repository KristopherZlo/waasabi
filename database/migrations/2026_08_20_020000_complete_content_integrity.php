<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('legal_version', 40)->nullable()->after('remember_token');
            $table->timestamp('legal_accepted_at')->nullable()->after('legal_version');
        });

        Schema::table('post_comments', function (Blueprint $table): void {
            $table->foreignId('post_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->integer('vote_score')->default(0)->after('useful');
        });
        Schema::table('post_reviews', function (Blueprint $table): void {
            $table->foreignId('post_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->integer('vote_score')->default(0)->after('how');
        });

        DB::table('post_comments')->orderBy('id')->each(function (object $comment): void {
            $postId = DB::table('posts')->where('slug', $comment->post_slug)->value('id');
            $postId
                ? DB::table('post_comments')->where('id', $comment->id)->update(['post_id' => $postId])
                : DB::table('post_comments')->where('id', $comment->id)->delete();
        });
        DB::table('post_reviews')->orderBy('id')->each(function (object $review): void {
            $postId = DB::table('posts')->where('slug', $review->post_slug)->value('id');
            $postId
                ? DB::table('post_reviews')->where('id', $review->id)->update(['post_id' => $postId])
                : DB::table('post_reviews')->where('id', $review->id)->delete();
        });

        Schema::table('post_comments', fn (Blueprint $table) => $table->unsignedBigInteger('post_id')->nullable(false)->change());
        Schema::table('post_reviews', fn (Blueprint $table) => $table->unsignedBigInteger('post_id')->nullable(false)->change());

        Schema::create('post_comment_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();
            $table->unique(['post_comment_id', 'user_id']);
        });
        Schema::create('post_review_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();
            $table->unique(['post_review_id', 'user_id']);
        });

        $duplicates = DB::table('content_reports')
            ->select('user_id', 'content_type', 'content_id', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('user_id')
            ->groupBy('user_id', 'content_type', 'content_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($duplicates as $duplicate) {
            DB::table('content_reports')
                ->where('user_id', $duplicate->user_id)
                ->where('content_type', $duplicate->content_type)
                ->where('content_id', $duplicate->content_id)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
        }
        Schema::table('content_reports', function (Blueprint $table): void {
            $table->unique(['user_id', 'content_type', 'content_id'], 'content_reports_reporter_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['privacy_share_activity', 'privacy_personalized_recommendations']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('privacy_share_activity')->default(true);
            $table->boolean('privacy_personalized_recommendations')->default(false);
        });
        Schema::table('content_reports', fn (Blueprint $table) => $table->dropUnique('content_reports_reporter_unique'));
        Schema::dropIfExists('post_review_votes');
        Schema::dropIfExists('post_comment_votes');
        Schema::table('post_reviews', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('post_id');
            $table->dropColumn('vote_score');
        });
        Schema::table('post_comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('post_id');
            $table->dropColumn('vote_score');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['legal_version', 'legal_accepted_at']));
    }
};
