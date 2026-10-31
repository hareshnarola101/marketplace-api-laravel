<?php

namespace App\Http\Requests\Vendor;

use App\Models\Vendor;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreVendorApplicationRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', Vendor::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
