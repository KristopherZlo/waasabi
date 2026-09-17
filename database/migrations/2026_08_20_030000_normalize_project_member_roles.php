<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_members')->where('role', 'Coauthor')->update(['role' => 'coauthor']);
        DB::table('project_members')->where('role', 'Contributor')->update(['role' => 'contributor']);
    }

    public function down(): void
    {
        DB::table('project_members')->where('role', 'coauthor')->update(['role' => 'Coauthor']);
        DB::table('project_members')->where('role', 'contributor')->update(['role' => 'Contributor']);
    }
};
