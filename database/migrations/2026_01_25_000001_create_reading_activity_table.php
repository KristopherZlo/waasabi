<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_activity', function (Blueprint $table) {
            $table->id();
            $table->string('post_id');
            $table->string('ip_hash', 64);
            $table->timestamps();

            $table->unique(['post_id', 'ip_hash']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_activity');
    }
};
