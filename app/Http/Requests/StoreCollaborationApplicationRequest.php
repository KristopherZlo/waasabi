<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollaborationApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publish') === true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:1200'],
            'applicant_post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'website' => ['nullable', 'string', 'max:40'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['message' => trim(strip_tags((string) $this->input('message'))) ?: __('waasabi.apply_message')]);
    }
}
