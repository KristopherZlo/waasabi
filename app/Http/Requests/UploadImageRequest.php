<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxImageKb = max(1, (int) config('waasabi.upload.max_image_mb', 5) * 1024);

        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxImageKb],
        ];
    }
}
