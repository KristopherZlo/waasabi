<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollaborationComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'collaboration_request_id',
        'collaboration_application_id',
        'user_id',
        'body',
    ];

    public function collaborationRequest(): BelongsTo
    {
        return $this->belongsTo(CollaborationRequest::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CollaborationApplication::class, 'collaboration_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
