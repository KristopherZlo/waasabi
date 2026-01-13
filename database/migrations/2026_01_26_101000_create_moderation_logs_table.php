<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('moderator_name');
            $table->string('moderator_role', 40)->default('moderator');
            $table->string('action', 60);
            $table->string('content_type', 40);
            $table->string('content_id', 190)->nullable();
            $table->string('content_url', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('location', 120)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['content_type', 'content_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
