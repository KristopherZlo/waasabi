<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'adminuser@wasabilabi.com';
        $name = 'admin';
        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'admin';
        }

        $existing = User::where('email', $email)->first();
        $slug = $existing?->slug;
        if (!$slug) {
            $slug = $baseSlug;
            $counter = 2;
            while (
                User::where('slug', $slug)
                    ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                    ->exists()
            ) {
                $slug = $baseSlug . '-' . $counter;
                $counter += 1;
            }
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'slug' => $slug,
                'password' => Hash::make('admin'),
                'role' => 'admin',
                'avatar' => '/images/avatar-default.svg',
                'email_verified_at' => now(),
            ],
        );
    }
}
