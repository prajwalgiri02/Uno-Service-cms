<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'area' => ['sometimes', 'nullable', 'string', 'max:80'],
            'language' => ['sometimes', 'string', Rule::in(['en', 'ne'])],
            'nudges_enabled' => ['sometimes', 'boolean'],
            'pings_per_day' => ['sometimes', 'integer', 'between:1,5'],
        ];
    }
}
