<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function lat(): float
    {
        return (float) $this->input('latitude');
    }

    public function lng(): float
    {
        return (float) $this->input('longitude');
    }
}
