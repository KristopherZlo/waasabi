<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaboration_applications', function (Blueprint $table): void {
            $table->foreignId('applicant_post_id')
                ->nullable()
                ->after('user_id')
                ->constrained('posts')
                ->nullOnDelete();
            $table->index(['applicant_post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_applications', function (Blueprint $table): void {
            $table->dropIndex(['applicant_post_id', 'status']);
            $table->dropConstrainedForeignId('applicant_post_id');
        });
    }
};
