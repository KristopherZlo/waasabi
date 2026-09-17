<?php

namespace App\Http\Requests;

use App\Services\CollaborationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollaborationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publish') === true;
    }

    public function rules(): array
    {
        $collaboration = app(CollaborationService::class);

        return [
            'post_id' => ['nullable', 'integer', Rule::exists('posts', 'id')->where('type', 'post')],
            'title' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_keys($collaboration->roleOptions()))],
            'availability' => ['required', Rule::in(array_keys($collaboration->availabilityOptions()))],
            'format' => ['required', Rule::in(array_keys($collaboration->formatOptions()))],
            'skills' => ['nullable', 'string', 'max:400'],
            'summary' => ['required', 'string', 'min:2', 'max:2000'],
            'expires_in_days' => ['nullable', 'integer', 'min:7', 'max:180'],
            'website' => ['nullable', 'string', 'max:40'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $role = $this->input('role');
        $roleLabel = is_string($role) ? (app(CollaborationService::class)->roleOptions()[$role] ?? 'collaborator') : 'collaborator';
        $this->merge([
            'title' => trim(strip_tags((string) $this->input('title'))) ?: __('waasabi.help_title', ['role' => $roleLabel]),
            'availability' => $this->input('availability') ?: 'one-time',
            'format' => $this->input('format') ?: 'remote',
            'skills' => trim(strip_tags((string) $this->input('skills'))),
            'summary' => trim(strip_tags((string) $this->input('summary'))),
        ]);
    }
}
