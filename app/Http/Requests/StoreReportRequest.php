<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'content_type' => ['required', 'string', 'max:40', 'in:post,comment,question,review,content'],
            'content_id' => ['nullable', 'string', 'max:190'],
            'content_url' => ['nullable', 'url', 'max:255'],
            'reason' => ['required', 'string', 'max:80', 'in:spam,abuse,offtopic,other,admin_flag'],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
