<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'edited_by',
        'type',
        'slug',
        'title',
        'subtitle',
        'body_markdown',
        'body_html',
        'media_url',
        'cover_url',
        'album_urls',
        'status',
        'nsfw',
        'is_hidden',
        'moderation_status',
        'hidden_at',
        'hidden_by',
        'tags',
        'coauthor_user_ids',
        'read_time_minutes',
    ];

    protected $casts = [
        'tags' => 'array',
        'album_urls' => 'array',
        'coauthor_user_ids' => 'array',
        'nsfw' => 'boolean',
        'is_hidden' => 'boolean',
        'hidden_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class, 'post_slug', 'slug');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PostReview::class, 'post_slug', 'slug');
    }

    public function upvoters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_upvotes', 'post_id', 'user_id')->withTimestamps();
    }

    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_saves', 'post_id', 'user_id')->withTimestamps();
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function hiddenBy()
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
