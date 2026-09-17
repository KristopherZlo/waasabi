<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_notifications')->whereIn('type', ['Security', 'Turvallisuus'])->delete();
    }

    public function down(): void {}
};
