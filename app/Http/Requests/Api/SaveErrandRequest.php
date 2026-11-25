<?php

namespace App\Http\Requests\Api;

use App\Services\ErrandService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveErrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'category_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('categories', 'id')->where('status', true)],
            'title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'variant' => ['sometimes', 'nullable', 'string', 'max:60'],
            'max_price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'radius_m' => ['sometimes', 'integer', Rule::in([500, 1000, 2000, 3000])],
            'only_no_wait' => ['sometimes', 'boolean'],
            'offers_only' => ['sometimes', 'boolean'],
            'women_run' => ['sometimes', 'boolean'],
            'duration' => ['sometimes', 'string', Rule::in(array_keys(ErrandService::DURATIONS))],
            'active' => [$creating ? 'prohibited' : 'sometimes', 'boolean'],
        ];
    }
}
