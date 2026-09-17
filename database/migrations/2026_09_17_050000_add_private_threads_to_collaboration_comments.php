<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaboration_comments', function (Blueprint $table): void {
            $table->foreignId('collaboration_application_id')->nullable()->after('collaboration_request_id')
                ->constrained('collaboration_applications')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collaboration_application_id');
        });
    }
};
