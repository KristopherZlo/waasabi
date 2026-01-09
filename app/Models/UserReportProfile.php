<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReportProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reports_submitted',
        'reports_confirmed',
        'reports_rejected',
        'reports_auto_hidden',
        'activity_points',
        'trust_score',
        'weight',
        'last_computed_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_computed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

