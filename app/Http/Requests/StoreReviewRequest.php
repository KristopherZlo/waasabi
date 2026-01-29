<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'improve' => ['required', 'string', 'max:2000'],
            'why' => ['required', 'string', 'max:2000'],
            'how' => ['required', 'string', 'max:2000'],
        ];
    }
}
