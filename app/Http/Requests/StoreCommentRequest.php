<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $parentRules = ['nullable', 'integer'];
        if (safeHasTable('post_comments')) {
            $parentRules[] = 'exists:post_comments,id';
        }

        return [
            'body' => ['required', 'string', 'max:2000'],
            'section' => ['nullable', 'string', 'max:80'],
            'parent_id' => $parentRules,
        ];
    }
}
