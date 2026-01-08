<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reporter_role',
        'role_weight',
        'reporter_weight',
        'reporter_trust',
        'weight',
        'content_type',
        'content_id',
        'content_url',
        'reason',
        'details',
        'resolved_status',
        'resolved_at',
        'auto_action',
        'meta',
    ];

    protected $casts = [
        'role_weight' => 'float',
        'reporter_weight' => 'float',
        'reporter_trust' => 'float',
        'weight' => 'float',
        'resolved_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
