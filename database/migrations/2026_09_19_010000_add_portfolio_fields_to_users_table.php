<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('headline', 160)->nullable()->after('bio');
            $table->text('profile_highlights')->nullable()->after('profile_readme');
            $table->json('profile_links')->nullable()->after('profile_highlights');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['headline', 'profile_highlights', 'profile_links']));
    }
};
