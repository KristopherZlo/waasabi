<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('slug', 120)->nullable()->after('name');
            $table->unique('slug');
        });

        $users = DB::table('users')->select('id', 'name', 'slug')->orderBy('id')->get();
        foreach ($users as $user) {
            if (!empty($user->slug)) {
                continue;
            }
            $base = Str::slug((string) $user->name);
            if ($base === '') {
                $base = 'user';
            }
            $slug = $base;
            $counter = 2;
            while (DB::table('users')->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $counter;
                $counter += 1;
            }
            DB::table('users')->where('id', $user->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
