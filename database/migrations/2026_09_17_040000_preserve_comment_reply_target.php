<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_comments', function (Blueprint $table): void {
            $table->foreignId('reply_to_id')->nullable()->constrained('post_comments')->nullOnDelete();
        });
        DB::table('post_comments')->whereNotNull('parent_id')->update(['reply_to_id' => DB::raw('parent_id')]);
    }

    public function down(): void
    {
        Schema::table('post_comments', fn (Blueprint $table) => $table->dropConstrainedForeignId('reply_to_id'));
    }
};
