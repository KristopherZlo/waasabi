<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('feedback_mode', 20)->default('sharing');
            $table->timestamp('activity_at')->nullable()->index();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('skills', 400)->nullable();
            $table->boolean('open_to_help')->default(false);
            $table->string('portfolio_url', 500)->nullable();
            $table->foreignId('featured_post_id')->nullable()->constrained('posts')->nullOnDelete();
        });
        Schema::table('collaboration_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('post_id')->nullable()->change();
        });
        Schema::create('project_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_follows');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_post_id');
            $table->dropColumn(['skills', 'open_to_help', 'portfolio_url']);
        });
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn(['feedback_mode', 'activity_at']));
        // Standalone help requests remain valid when rolling back this feature.
    }
};
