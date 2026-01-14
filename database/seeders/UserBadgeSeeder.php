<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserBadgeSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('user_badges')) {
            return;
        }

        $user = User::where('slug', 'zloy')->first();
        if (!$user) {
            return;
        }

        $existing = DB::table('user_badges')->where('user_id', $user->id)->count();
        if ($existing > 0) {
            return;
        }

        $issuedBy = User::where('role', 'admin')->value('id');
        $rows = [
            [
                'badge_key' => 'community_hero',
                'reason' => 'Помощь новичкам',
                'issued_at' => '2026-01-05 10:00:00',
            ],
            [
                'badge_key' => 'community_hero',
                'reason' => 'Разбор сложной темы',
                'issued_at' => '2026-01-12 10:00:00',
            ],
            [
                'badge_key' => 'community_hero',
                'reason' => 'Лучшие ответы месяца',
                'issued_at' => '2026-01-18 10:00:00',
            ],
            [
                'badge_key' => 'community_hero',
                'reason' => 'Менторинг участников',
                'issued_at' => '2026-01-22 10:00:00',
            ],
            [
                'badge_key' => 'community_hero',
                'reason' => 'Репорты по делу',
                'issued_at' => '2026-01-24 10:00:00',
            ],
        ];

        $now = now();
        foreach ($rows as $row) {
            DB::table('user_badges')->insert([
                'user_id' => $user->id,
                'badge_key' => $row['badge_key'],
                'badge_name' => null,
                'badge_description' => null,
                'reason' => $row['reason'],
                'issued_by' => $issuedBy,
                'issued_at' => $row['issued_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
