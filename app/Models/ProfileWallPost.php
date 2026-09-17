<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileWallPost extends Model
{
    protected $fillable = ['profile_user_id', 'user_id', 'body', 'is_hidden', 'moderation_status'];

    protected $casts = ['is_hidden' => 'boolean'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profile_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
