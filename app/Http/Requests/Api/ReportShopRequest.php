<?php

namespace App\Http\Requests\Api;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'note' => [Rule::requiredIf($this->input('reason') === ReportReason::OTHER->value), 'nullable', 'string', 'max:1000'],
        ];
    }
}
