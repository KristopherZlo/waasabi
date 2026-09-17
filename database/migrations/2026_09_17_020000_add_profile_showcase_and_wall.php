<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('profile_readme')->nullable();
            $table->string('wall_mode', 20)->default('everyone');
        });

        Schema::create('profile_showcase_projects', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);
            $table->primary(['user_id', 'post_id']);
        });

        Schema::create('profile_wall_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_hidden')->default(false);
            $table->string('moderation_status', 20)->default('approved');
            $table->timestamps();
            $table->index(['profile_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_wall_posts');
        Schema::dropIfExists('profile_showcase_projects');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['profile_readme', 'wall_mode']));
    }
};
