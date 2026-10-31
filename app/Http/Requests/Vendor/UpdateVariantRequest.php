<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVariantRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'integer', 'min:1', 'max:100000000'],
            'stock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
