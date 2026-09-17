<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')->where('type', 'question')->update(['is_project' => false]);
        DB::table('posts')->whereIn('slug', [
            'fast-breakdown',
            'night-bus-photo-essay',
            'procedural-moss-game',
        ])->update(['is_project' => false]);
    }

    public function down(): void
    {
        DB::table('posts')->where('type', 'question')->update(['is_project' => true]);
        DB::table('posts')->whereIn('slug', [
            'fast-breakdown',
            'night-bus-photo-essay',
            'procedural-moss-game',
        ])->update(['is_project' => true]);
    }
};
