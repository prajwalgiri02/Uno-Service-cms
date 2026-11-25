<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SaveFreeWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:40'],
            'weekdays' => [$required, 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'from' => [$required, 'date_format:H:i'],
            'to' => [$required, 'date_format:H:i', 'after:from'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
