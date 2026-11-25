<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class OptionalLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    public function hasLocation(): bool
    {
        return $this->filled('latitude') && $this->filled('longitude');
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
