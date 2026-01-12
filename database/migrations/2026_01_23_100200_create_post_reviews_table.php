<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('post_slug', 190)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('improve');
            $table->text('why');
            $table->text('how');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reviews');
    }
};
