<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NepaliMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Phone::isValid($value)) {
            $fail('Enter a valid 10-digit mobile number starting with 97 or 98.');
        }
    }
}
