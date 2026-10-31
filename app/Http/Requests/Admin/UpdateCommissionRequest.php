<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Null resets the vendor to the platform default rate.
            'commission_rate_bps' => ['present', 'nullable', 'integer', 'between:0,10000'],
        ];
    }
}
