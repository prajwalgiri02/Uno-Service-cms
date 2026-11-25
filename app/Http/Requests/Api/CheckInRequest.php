<?php

namespace App\Http\Requests\Api;

class CheckInRequest extends LocationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'max:20'],
            'shop_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
