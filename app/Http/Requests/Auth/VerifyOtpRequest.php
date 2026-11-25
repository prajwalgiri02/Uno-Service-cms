<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends SendOtpRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'digits:'.config('otp.defaults.length')],
            'as' => ['sometimes', 'string', Rule::in(UserRole::appRoles())],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }

    public function signInAs(): UserRole
    {
        return UserRole::from($this->input('as', UserRole::CUSTOMER->value));
    }
}
