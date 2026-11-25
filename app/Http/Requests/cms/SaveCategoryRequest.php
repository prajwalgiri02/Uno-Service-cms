<?php

namespace App\Http\Requests\cms;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')->ignore($category?->id)],
            'name_ne' => ['nullable', 'string', 'max:80'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'emoji' => ['nullable', 'string', 'max:16'],
            'options' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'typical_price_min' => ['nullable', 'integer', 'min:0'],
            'typical_price_max' => ['nullable', 'integer', 'min:0', 'gte:typical_price_min'],
            'status' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A category with this name already exists.',
            'parent_id.exists' => 'Pick a main category as the parent.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $category = $this->route('category');

                if ($category instanceof Category && $this->filled('parent_id') && $category->children()->exists()) {
                    $validator->errors()->add('parent_id', 'A main category with subcategories cannot become a subcategory.');
                }

                if ($category instanceof Category && (int) $this->input('parent_id') === $category->id) {
                    $validator->errors()->add('parent_id', 'A category cannot be its own parent.');
                }
            },
        ];
    }

    /**
     * Options and keywords arrive comma-separated from the form.
     *
     * @return array<string, mixed>
     */
    public function attributesToSave(): array
    {
        $split = fn (?string $value) => collect(explode(',', (string) $value))->map(fn ($v) => trim($v))->filter()->values()->all();

        return [
            ...collect($this->validated())->except(['options', 'keywords'])->all(),
            'parent_id' => $this->input('parent_id') ?: null,
            'sort_order' => (int) $this->input('sort_order', 0),
            'options' => $split($this->input('options')),
            'keywords' => $split($this->input('keywords')),
        ];
    }
}
