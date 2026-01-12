<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('post');
            $table->string('slug', 190)->unique();
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->longText('body_markdown')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('media_url', 255)->nullable();
            $table->string('cover_url', 255)->nullable();
            $table->string('status', 40)->nullable();
            $table->json('tags')->nullable();
            $table->unsignedInteger('read_time_minutes')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
