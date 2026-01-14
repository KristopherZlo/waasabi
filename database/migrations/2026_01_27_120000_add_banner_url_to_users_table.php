<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'banner_url')) {
                $table->string('banner_url', 255)->nullable()->after('avatar');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'banner_url')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('banner_url');
        });
    }
};

