<?php

namespace App\Http\Requests\Auth;

use App\Rules\NepaliMobileNumber;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20', new NepaliMobileNumber],
        ];
    }

    public function phone(): string
    {
        return Phone::normalize($this->string('phone')->toString());
    }
}
