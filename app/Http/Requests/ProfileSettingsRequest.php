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
            'avatar_file' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:1024',
                'dimensions:min_width=512,min_height=512,max_width=1024,max_height=1024',
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
            'skills' => ['nullable', 'string', 'max:400'],
            'open_to_help' => ['nullable', 'boolean'],
            'portfolio_url' => ['nullable', 'url:http,https', 'max:500'],
            'featured_post_id' => ['nullable', 'integer', Rule::exists('posts', 'id')->where('user_id', $this->user()->id)->where('type', 'post')],
            'profile_readme' => ['nullable', 'string', 'max:5000'],
            'showcase_project_ids' => ['nullable', 'array', 'max:6'],
            'showcase_project_ids.*' => ['integer', 'distinct', Rule::exists('posts', 'id')->where('user_id', $this->user()->id)->where('type', 'post')->where('is_project', true)],
            'showcase_project_ids_present' => ['nullable', 'boolean'],
            'wall_mode' => ['nullable', Rule::in(['everyone', 'owner'])],
            'privacy_allow_mentions' => ['nullable', 'boolean'],
            'notify_comments' => ['nullable', 'boolean'],
            'notify_reviews' => ['nullable', 'boolean'],
            'notify_follows' => ['nullable', 'boolean'],
            'connections_allow_follow' => ['nullable', 'boolean'],
            'connections_show_follow_counts' => ['nullable', 'boolean'],
            'security_login_alerts' => ['nullable', 'boolean'],
            'return_to_profile' => ['nullable', 'boolean'],
        ];
    }
}
