<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('posts') || Schema::hasColumn('posts', 'coauthor_user_ids')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table): void {
            $table->json('coauthor_user_ids')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('posts') || !Schema::hasColumn('posts', 'coauthor_user_ids')) {
            return;
        }

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('coauthor_user_ids');
        });
    }
};

