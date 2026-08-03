<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreWasteScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'detected_location_code' => ['required', 'string', 'max:100'],
            'logistic_unit_code' => ['nullable', 'string', 'max:100'],
            'is_waste_pallet' => ['boolean'],
            'material_id' => ['nullable', 'integer', 'exists:inventory_materials,id'],
            'position_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'waste_reason_id' => ['required', 'integer', 'exists:inventory_waste_reasons,id'],
            'waste_type_id' => ['required', 'integer', 'exists:inventory_waste_types,id'],
            'quarantine_location_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'fecha_movimiento' => ['nullable', 'date'],
            'scan_session_uuid' => ['nullable', 'uuid'],
            'device_code' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasLogisticUnit = filled($this->input('logistic_unit_code'));
            $hasMaterial = filled($this->input('material_id'));

            if (! $hasLogisticUnit && ! $hasMaterial) {
                $validator->errors()->add('material_id', 'Debes indicar un pallet o un material.');
            }
        });
    }
}
