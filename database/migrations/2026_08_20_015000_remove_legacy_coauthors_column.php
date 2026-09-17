<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('posts', 'coauthor_user_ids')) {
            Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('coauthor_user_ids'));
        }
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->json('coauthor_user_ids')->nullable());
    }
};
