<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'url', 'max:255'],
            'avatar_file' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'min:512',
                'max:1024',
                'dimensions:min_width=512,min_height=512,max_width=1024,max_height=1024',
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
            'role' => ['nullable', Rule::in(config('roles.order', ['user', 'maker', 'moderator', 'admin']))],
            'privacy_share_activity' => ['nullable', 'boolean'],
            'privacy_allow_mentions' => ['nullable', 'boolean'],
            'privacy_personalized_recommendations' => ['nullable', 'boolean'],
            'notify_comments' => ['nullable', 'boolean'],
            'notify_reviews' => ['nullable', 'boolean'],
            'notify_follows' => ['nullable', 'boolean'],
            'connections_allow_follow' => ['nullable', 'boolean'],
            'connections_show_follow_counts' => ['nullable', 'boolean'],
            'security_login_alerts' => ['nullable', 'boolean'],
        ];
    }
}
