<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ApiLogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
