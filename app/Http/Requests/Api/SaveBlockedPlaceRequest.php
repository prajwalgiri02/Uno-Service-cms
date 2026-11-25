<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SaveBlockedPlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:60'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'radius_m' => ['sometimes', 'integer', 'between:50,1000'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
