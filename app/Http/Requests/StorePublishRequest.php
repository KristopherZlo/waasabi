<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publish') === true;
    }

    public function rules(): array
    {
        $maxImageKb = max(1, (int) config('hub.upload.max_image_mb', 5) * 1024);
        $maxCoverImages = max(1, (int) config('hub.upload.max_images_per_post', 8));
        $maxAttachments = max(1, (int) config('projects.attachment_max_count', 8));
        $maxAttachmentKb = max(1, (int) config('projects.attachment_max_kb', 51200));
        $isDraft = $this->input('publish_action') === 'draft';

        return [
            'publish_type' => ['required', Rule::in(['post', 'question'])],
            'is_project' => ['nullable', 'boolean'],
            'post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'feedback_mode' => ['nullable', Rule::in(['sharing', 'feedback', 'help'])],
            'category' => ['required_if:publish_type,post', 'nullable', Rule::in(array_keys(config('projects.categories', [])))],
            'media_type' => ['required_if:publish_type,post', 'nullable', Rule::in(array_keys(config('projects.media_types', [])))],
            'license' => ['required_if:publish_type,post', 'nullable', Rule::in(array_keys(config('projects.licenses', [])))],
            'external_url' => ['nullable', 'url:http,https', 'max:500'],
            'repository_url' => ['nullable', 'url:http,https', 'max:500'],
            'visibility' => ['nullable', Rule::in(['public', 'unlisted'])],
            'publish_action' => ['nullable', Rule::in(['publish', 'draft'])],
            'coauthors' => ['nullable', 'string', 'max:600'],
            'cover_images' => ['nullable', 'array', 'max:'.$maxCoverImages],
            'cover_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxImageKb],
            'attachments' => ['nullable', 'array', 'max:'.$maxAttachments],
            'attachments.*' => [
                'file',
                'mimes:mp3,wav,ogg,flac,m4a,mp4,webm,mov,pdf,zip,txt,md',
                'max:'.$maxAttachmentKb,
            ],
            'remove_attachment_ids' => ['nullable', 'array'],
            'remove_attachment_ids.*' => ['integer'],
            'status' => ['nullable', Rule::in(config('projects.statuses'))],
            'nsfw' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'string', 'max:255'],
            'body' => $isDraft
                ? ['nullable', 'string', 'max:100000']
                : ['required_if:publish_type,post', 'nullable', 'string', 'max:100000'],
            'question_body' => $isDraft
                ? ['nullable', 'string', 'max:2000']
                : ['required_if:publish_type,question', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('publish_type') !== 'post') {
            return;
        }

        $this->merge([
            'category' => $this->input('category') ?: 'other',
            'media_type' => $this->input('media_type') ?: 'mixed',
            'license' => $this->input('license') ?: 'all-rights-reserved',
            'visibility' => $this->input('visibility') ?: 'public',
            'publish_action' => $this->input('publish_action') ?: 'publish',
        ]);
    }
}
