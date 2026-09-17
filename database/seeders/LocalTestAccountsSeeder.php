<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class LocalTestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('Local test accounts were not created outside the local environment.');

            return;
        }

        foreach ([
            ['name' => 'Admin', 'slug' => 'quick-admin', 'email' => 'admin@hub.test', 'role' => 'admin'],
            ['name' => 'User', 'slug' => 'quick-user', 'email' => 'user@hub.test', 'role' => 'user'],
        ] as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                $account + [
                    'password' => '123',
                    'email_verified_at' => now(),
                    'legal_version' => config('hub.legal_version'),
                    'legal_accepted_at' => now(),
                    'created_at' => now()->subDay(),
                ],
            );
        }
    }
}
