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
            'content_type' => ['required', 'string', 'max:40', 'in:post,comment,question,review,profile,collaboration,collaboration_comment'],
            'content_id' => ['required', 'string', 'max:190'],
            'content_url' => ['exclude'],
            'reason' => ['required', 'string', 'max:80', 'in:spam,abuse,offtopic,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
