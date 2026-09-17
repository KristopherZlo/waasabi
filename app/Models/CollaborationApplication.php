<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollaborationApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'collaboration_request_id',
        'user_id',
        'applicant_post_id',
        'message',
        'status',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function collaborationRequest(): BelongsTo
    {
        return $this->belongsTo(CollaborationRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applicantPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'applicant_post_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CollaborationComment::class, 'collaboration_application_id')->oldest();
    }
}
