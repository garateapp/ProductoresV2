<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLogisticUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $logisticUnit = $this->route('logisticUnit');

        return [
            'license_plate_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('inventory_logistic_units', 'license_plate_number')->ignore($logisticUnit),
            ],
            'material_id' => ['sometimes', 'integer', 'exists:inventory_materials,id'],
            'current_location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'spatial_prefix' => ['nullable', 'string', 'max:50'],
            'spatial_column' => ['nullable', 'string', 'max:50'],
            'spatial_row' => ['nullable', 'string', 'max:50'],
            'base_quantity' => ['sometimes', 'numeric', 'gt:0'],
            'available_quantity' => ['sometimes', 'numeric', 'gte:0'],
            'unit_id' => ['nullable', 'integer', 'exists:inventory_units,id'],
            'lot_code' => ['nullable', 'string', 'max:100'],
            'supplier_lot' => ['nullable', 'string', 'max:100'],
            'production_batch' => ['nullable', 'string', 'max:100'],
            'dispatch_guide' => ['nullable', 'string', 'max:100'],
        ];
    }
}
