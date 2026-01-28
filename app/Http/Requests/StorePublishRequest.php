<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxImageKb = max(1, (int) config('waasabi.upload.max_image_mb', 5) * 1024);
        $maxCoverImages = max(1, (int) config('waasabi.upload.max_images_per_post', 8));

        $postIdRules = ['nullable', 'integer'];
        if (safeHasTable('posts')) {
            $postIdRules[] = 'exists:posts,id';
        }

        return [
            'publish_type' => ['required', Rule::in(['post', 'question'])],
            'post_id' => $postIdRules,
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'coauthors' => ['nullable', 'string', 'max:600'],
            'cover_images' => ['nullable', 'array', 'max:' . $maxCoverImages],
            'cover_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxImageKb],
            'status' => ['nullable', 'string', 'max:40'],
            'nsfw' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'string', 'max:255'],
            'body' => ['required_if:publish_type,post', 'nullable', 'string'],
            'question_body' => ['required_if:publish_type,question', 'nullable', 'string', 'max:2000'],
        ];
    }
}
