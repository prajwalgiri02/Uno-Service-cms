<?php

namespace App\Http\Requests\Vendor;

use App\Models\ShopService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveServiceRequest extends FormRequest
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
            'for_who' => ['sometimes', 'nullable', Rule::in(['men', 'women', 'kids', 'all'])],
            'minutes' => [$required, 'integer', 'between:1,600'],
            'price_min' => [$required, 'integer', 'min:0', 'max:1000000'],
            'price_max' => [$required, 'integer', 'min:0', 'max:1000000', $this->notBelowMinimum(...)],
            'walk_ins' => ['sometimes', 'boolean'],
            'photo' => ['sometimes', 'image', 'max:5120'],
        ];
    }

    /**
     * On a partial update the lower bound may come from the saved service.
     */
    private function notBelowMinimum(string $attribute, mixed $value, Closure $fail): void
    {
        $service = $this->route('service');
        $min = $this->input('price_min', $service instanceof ShopService ? $service->price_min : null);

        if ($min !== null && is_numeric($min) && (int) $value < (int) $min) {
            $fail('The first number should be the lower one.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesToSave(): array
    {
        return collect($this->validated())->except('photo')->all();
    }
}
