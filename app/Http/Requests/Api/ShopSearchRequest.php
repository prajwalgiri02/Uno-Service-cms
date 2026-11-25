<?php

namespace App\Http\Requests\Api;

class ShopSearchRequest extends LocationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'radius_m' => ['sometimes', 'integer', 'between:100,5000'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:60'],
            'on_list' => ['sometimes', 'boolean'],
            'no_wait' => ['sometimes', 'boolean'],
            'offers' => ['sometimes', 'boolean'],
            'open_now' => ['sometimes', 'boolean'],
            'women_run' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{no_wait: bool, offers: bool, open_now: bool, women_run: bool, search: string|null}
     */
    public function filters(): array
    {
        return [
            'no_wait' => $this->boolean('no_wait'),
            'offers' => $this->boolean('offers'),
            'open_now' => $this->boolean('open_now'),
            'women_run' => $this->boolean('women_run'),
            'search' => $this->input('search'),
        ];
    }
}
