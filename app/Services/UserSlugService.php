<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class UserSlugService
{
    public function generate(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'user';
        }

        $slug = $base;
        $counter = 2;
        while (User::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter += 1;
        }

        return $slug;
    }
}
