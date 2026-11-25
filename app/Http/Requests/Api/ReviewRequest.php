<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stars' => ['required', 'integer', 'between:1,5'],
            'text' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
