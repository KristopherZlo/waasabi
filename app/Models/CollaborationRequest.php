<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollaborationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'title',
        'role',
        'skills',
        'availability',
        'format',
        'summary',
        'status',
        'expires_at',
        'closed_at',
    ];

    protected $casts = [
        'skills' => 'array',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CollaborationApplication::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CollaborationComment::class)->whereNull('collaboration_application_id');
    }

    public function scopeVisibleTo($query, ?User $viewer)
    {
        return $query->whereHas('user', fn ($q) => $q->where('is_banned', false))
            ->where(function ($query) use ($viewer): void {
                $query->whereNull('post_id')->orWhereHas('post', function ($q) use ($viewer): void {
                    $q->where('type', 'post')->whereHas('user', fn ($u) => $u->where('is_banned', false));
                    if (! $viewer?->hasRole('moderator')) {
                        $q->where('visibility', 'public')->where('is_hidden', false)->where('moderation_status', 'approved');
                    }
                });
            });
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
