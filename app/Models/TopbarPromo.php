<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopbarPromo extends Model
{
    protected $fillable = [
        'label',
        'url',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
        'max_impressions',
        'impressions_count',
        'clicks_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_impressions' => 'integer',
        'impressions_count' => 'integer',
        'clicks_count' => 'integer',
    ];
}
