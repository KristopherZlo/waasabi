<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostReview extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PostReview $review): void {
            $review->post_id ??= Post::query()->where('slug', $review->post_slug)->value('id');
        });
    }

    protected $fillable = [
        'post_id',
        'post_slug',
        'user_id',
        'improve',
        'why',
        'how',
        'vote_score',
        'is_hidden',
        'moderation_status',
        'hidden_at',
        'hidden_by',
    ];

    protected $casts = [
        'vote_score' => 'integer',
        'is_hidden' => 'boolean',
        'hidden_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
