<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            if (!Schema::hasColumn('support_tickets', 'response')) {
                $table->text('response')->nullable()->after('body');
            }
            if (!Schema::hasColumn('support_tickets', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('response');
            }
            if (!Schema::hasColumn('support_tickets', 'responded_by')) {
                $table->foreignId('responded_by')->nullable()->after('responded_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('support_tickets', 'responded_by')) {
                $table->dropConstrainedForeignId('responded_by');
            }
            if (Schema::hasColumn('support_tickets', 'responded_at')) {
                $table->dropColumn('responded_at');
            }
            if (Schema::hasColumn('support_tickets', 'response')) {
                $table->dropColumn('response');
            }
        });
    }
};
