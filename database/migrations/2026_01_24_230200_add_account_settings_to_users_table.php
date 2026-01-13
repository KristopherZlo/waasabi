<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('privacy_share_activity')->default(true)->after('bio');
            $table->boolean('privacy_allow_mentions')->default(true)->after('privacy_share_activity');
            $table->boolean('privacy_personalized_recommendations')->default(false)->after('privacy_allow_mentions');
            $table->boolean('notify_comments')->default(true)->after('privacy_personalized_recommendations');
            $table->boolean('notify_reviews')->default(true)->after('notify_comments');
            $table->boolean('notify_follows')->default(true)->after('notify_reviews');
            $table->boolean('connections_allow_follow')->default(true)->after('notify_follows');
            $table->boolean('connections_show_follow_counts')->default(true)->after('connections_allow_follow');
            $table->boolean('security_login_alerts')->default(true)->after('connections_show_follow_counts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'privacy_share_activity',
                'privacy_allow_mentions',
                'privacy_personalized_recommendations',
                'notify_comments',
                'notify_reviews',
                'notify_follows',
                'connections_allow_follow',
                'connections_show_follow_counts',
                'security_login_alerts',
            ]);
        });
    }
};
