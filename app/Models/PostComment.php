<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostComment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PostComment $comment): void {
            $comment->post_id ??= Post::query()->where('slug', $comment->post_slug)->value('id');
        });
    }

    protected $fillable = [
        'post_id',
        'post_slug',
        'user_id',
        'body',
        'section',
        'useful',
        'vote_score',
        'parent_id',
        'reply_to_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'reply_to_id');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
