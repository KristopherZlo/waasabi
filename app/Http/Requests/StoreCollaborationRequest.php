<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
class StoreCollaborationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if (safeHasColumn('users', 'is_banned') && ($user->is_banned ?? false)) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:40'],
            'availability' => ['required', 'string', 'max:30'],
            'format' => ['required', 'string', 'max:30'],
            'skills' => ['nullable', 'string', 'max:180'],
            'summary' => ['required', 'string', 'min:40', 'max:1200'],
            'contact' => ['nullable', 'string', 'max:160'],
            'website' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'summary.min' => __('validation.min.string', ['attribute' => 'summary', 'min' => 40]),
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['role'] = trim((string) ($data['role'] ?? ''));
        $data['availability'] = trim((string) ($data['availability'] ?? ''));
        $data['format'] = trim((string) ($data['format'] ?? ''));
        $data['skills'] = trim((string) ($data['skills'] ?? ''));
        $data['summary'] = trim(strip_tags((string) ($data['summary'] ?? '')));
        $data['contact'] = trim(strip_tags((string) ($data['contact'] ?? '')));
        $data['website'] = trim((string) ($data['website'] ?? ''));

        return $data;
    }
}
