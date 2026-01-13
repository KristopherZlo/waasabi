<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false);
            $table->string('moderation_status', 20)->default('approved');
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('is_hidden');
            $table->index('moderation_status');
        });

        Schema::table('post_comments', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false);
            $table->string('moderation_status', 20)->default('approved');
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('is_hidden');
            $table->index('moderation_status');
        });

        Schema::table('post_reviews', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false);
            $table->string('moderation_status', 20)->default('approved');
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('is_hidden');
            $table->index('moderation_status');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropIndex(['is_hidden']);
            $table->dropIndex(['moderation_status']);
            $table->dropColumn(['is_hidden', 'moderation_status', 'hidden_at']);
        });

        Schema::table('post_comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropIndex(['is_hidden']);
            $table->dropIndex(['moderation_status']);
            $table->dropColumn(['is_hidden', 'moderation_status', 'hidden_at']);
        });

        Schema::table('post_reviews', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropIndex(['is_hidden']);
            $table->dropIndex(['moderation_status']);
            $table->dropColumn(['is_hidden', 'moderation_status', 'hidden_at']);
        });
    }
};
