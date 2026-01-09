<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentReportScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_type',
        'content_id',
        'reports_count',
        'reporters_count',
        'weight_total',
        'weight_threshold',
        'site_scale',
        'auto_hidden_at',
        'last_report_at',
        'last_recomputed_at',
        'meta',
    ];

    protected $casts = [
        'auto_hidden_at' => 'datetime',
        'last_report_at' => 'datetime',
        'last_recomputed_at' => 'datetime',
        'meta' => 'array',
    ];
}

