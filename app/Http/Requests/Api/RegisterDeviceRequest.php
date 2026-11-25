<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:191'],
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['sometimes', 'nullable', Rule::in(['android', 'ios', 'web'])],
            'notifications_allowed' => ['sometimes', 'boolean'],
            'location_permission' => ['sometimes', 'nullable', Rule::in(['always', 'while_using', 'denied'])],
        ];
    }
}
