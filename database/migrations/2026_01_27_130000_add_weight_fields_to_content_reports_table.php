<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_reports', function (Blueprint $table): void {
            $table->string('reporter_role', 40)->nullable()->after('user_id');
            $table->double('role_weight')->default(1)->after('reporter_role');
            $table->double('reporter_weight')->default(1)->after('role_weight');
            $table->double('reporter_trust')->default(1)->after('reporter_weight');
            $table->double('weight')->default(1)->after('reporter_trust');
            $table->string('resolved_status', 40)->default('pending')->after('details');
            $table->timestamp('resolved_at')->nullable()->after('resolved_status');
            $table->string('auto_action', 40)->nullable()->after('resolved_at');
            $table->json('meta')->nullable()->after('auto_action');

            $table->index('resolved_status');
        });
    }

    public function down(): void
    {
        Schema::table('content_reports', function (Blueprint $table): void {
            $table->dropIndex(['resolved_status']);
            $table->dropColumn([
                'reporter_role',
                'role_weight',
                'reporter_weight',
                'reporter_trust',
                'weight',
                'resolved_status',
                'resolved_at',
                'auto_action',
                'meta',
            ]);
        });
    }
};

