<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:3000'],
            'as_vendor' => ['sometimes', 'boolean'],
        ];
    }
}
