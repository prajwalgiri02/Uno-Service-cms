<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class StartOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'percent' => ['required', 'integer', 'between:5,50'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'from' => ['required', 'date_format:H:i'],
            'to' => ['required', 'date_format:H:i', 'after:from'],
        ];
    }
}
