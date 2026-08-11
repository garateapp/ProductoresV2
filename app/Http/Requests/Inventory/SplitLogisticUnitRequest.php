<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class SplitLogisticUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pallet_count' => ['required', 'integer', 'min:2', 'max:100'],
            'spatial_prefix' => ['nullable', 'string', 'max:50'],
            'spatial_column' => ['nullable', 'string', 'max:50'],
            'spatial_row' => ['nullable', 'string', 'max:50'],
        ];
    }
}
