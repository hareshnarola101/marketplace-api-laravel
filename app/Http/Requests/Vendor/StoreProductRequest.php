<?php

namespace App\Http\Requests\Vendor;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'variants' => ['required', 'array', 'min:1', 'max:50'],
            'variants.*.sku' => ['required', 'string', 'max:64', 'distinct', 'unique:product_variants,sku'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.price' => ['required', 'integer', 'min:1', 'max:100000000'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /** @return array<string, mixed> */
    public function productData(): array
    {
        return [
            ...$this->safe()->only(['name', 'description', 'variants']),
            'status' => $this->validated('status', ProductStatus::Draft->value),
        ];
    }
}
