<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostReview extends Model
{
    protected $fillable = [
        'post_slug',
        'user_id',
        'improve',
        'why',
        'how',
        'is_hidden',
        'moderation_status',
        'hidden_at',
        'hidden_by',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'hidden_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
