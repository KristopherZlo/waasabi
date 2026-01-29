<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminSupportTicketResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', Rule::in(['open', 'waiting', 'closed'])],
        ];
    }
}
