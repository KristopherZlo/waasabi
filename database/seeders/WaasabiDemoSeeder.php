<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class WaasabiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Demo content is only available locally.');
        }

        $this->call(DatabaseSeeder::class);
    }
}
