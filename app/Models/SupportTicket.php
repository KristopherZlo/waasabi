<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kind',
        'subject',
        'body',
        'response',
        'status',
        'meta',
        'responded_at',
        'responded_by',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
