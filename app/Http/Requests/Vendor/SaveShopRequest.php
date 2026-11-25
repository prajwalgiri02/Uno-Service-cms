<?php

namespace App\Http\Requests\Vendor;

use App\Models\Shop;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:80'],
            'category_id' => [$required, 'integer', Rule::exists('categories', 'id')->where('status', true)],
            'latitude' => [$required, 'numeric', 'between:-90,90'],
            'longitude' => [$required, 'numeric', 'between:-180,180'],
            'area' => ['sometimes', 'nullable', 'string', 'max:80'],
            'landmark' => ['sometimes', 'nullable', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'blurb' => ['sometimes', 'nullable', 'string', 'max:120'],
            'vibes' => ['sometimes', 'array', 'max:5'],
            'vibes.*' => ['string', 'max:30'],
            'women_run' => ['sometimes', 'boolean'],
            'opening_hours' => ['sometimes', 'nullable', 'array', $this->validHours(...)],
        ];
    }

    /**
     * Keys mon…sun, each null (closed) or {open: "HH:MM", close: "HH:MM"} with open before close.
     */
    private function validHours(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($value as $day => $hours) {
            if (! in_array($day, Shop::WEEKDAYS, true)) {
                $fail("Unknown day \"{$day}\" in opening hours.");

                return;
            }

            if ($hours === null) {
                continue;
            }

            $valid = is_array($hours)
                && preg_match('/^\d{2}:\d{2}$/', (string) ($hours['open'] ?? ''))
                && preg_match('/^\d{2}:\d{2}$/', (string) ($hours['close'] ?? ''))
                && $hours['open'] < $hours['close'];

            if (! $valid) {
                $fail("Opening hours for {$day} need an open time before the close time.");

                return;
            }
        }
    }
}
