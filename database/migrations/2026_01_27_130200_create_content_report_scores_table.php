<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_report_scores', function (Blueprint $table): void {
            $table->id();
            $table->string('content_type', 40);
            $table->string('content_id', 190);
            $table->unsignedInteger('reports_count')->default(0);
            $table->unsignedInteger('reporters_count')->default(0);
            $table->double('weight_total')->default(0);
            $table->double('weight_threshold')->default(0);
            $table->double('site_scale')->default(1);
            $table->timestamp('auto_hidden_at')->nullable();
            $table->timestamp('last_report_at')->nullable();
            $table->timestamp('last_recomputed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['content_type', 'content_id']);
            $table->index('auto_hidden_at');
            $table->index('weight_total');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_report_scores');
    }
};

