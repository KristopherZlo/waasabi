<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topbar_promos', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('sort_order');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->unsignedInteger('max_impressions')->nullable()->after('ends_at');
            $table->unsignedInteger('impressions_count')->default(0)->after('max_impressions');
        });
    }

    public function down(): void
    {
        Schema::table('topbar_promos', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at', 'max_impressions', 'impressions_count']);
        });
    }
};
