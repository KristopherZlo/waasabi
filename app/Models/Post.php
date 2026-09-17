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
        'is_project',
        'feedback_mode',
        'activity_at',
        'category',
        'media_type',
        'license',
        'slug',
        'title',
        'subtitle',
        'body_markdown',
        'body_html',
        'media_url',
        'external_url',
        'repository_url',
        'cover_url',
        'album_urls',
        'status',
        'visibility',
        'published_at',
        'nsfw',
        'is_hidden',
        'moderation_status',
        'hidden_at',
        'hidden_by',
        'tags',
        'read_time_minutes',
    ];

    protected $casts = [
        'is_project' => 'boolean',
        'activity_at' => 'datetime',
        'tags' => 'array',
        'album_urls' => 'array',
        'nsfw' => 'boolean',
        'is_hidden' => 'boolean',
        'hidden_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PostReview::class);
    }

    public function collaborationRequests(): HasMany
    {
        return $this->hasMany(CollaborationRequest::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PostAttachment::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class);
    }

    public function upvoters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_upvotes', 'post_id', 'user_id')->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_follows')->withTimestamps();
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
