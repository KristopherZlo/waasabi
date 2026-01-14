<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_report_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('reports_submitted')->default(0);
            $table->unsignedInteger('reports_confirmed')->default(0);
            $table->unsignedInteger('reports_rejected')->default(0);
            $table->unsignedInteger('reports_auto_hidden')->default(0);
            $table->double('activity_points')->default(0);
            $table->double('trust_score')->default(1);
            $table->double('weight')->default(1);
            $table->timestamp('last_computed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('weight');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_report_profiles');
    }
};

