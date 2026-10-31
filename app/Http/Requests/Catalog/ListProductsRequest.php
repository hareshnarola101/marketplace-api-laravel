<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'vendor' => ['sometimes', 'string', 'max:255'],
            'min_price' => ['sometimes', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'integer', 'min:0', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'in_stock' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'in:newest,price,-price'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
