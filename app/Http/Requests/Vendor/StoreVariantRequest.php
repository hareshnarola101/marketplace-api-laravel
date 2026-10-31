<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', 'unique:product_variants,sku'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:1', 'max:100000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
