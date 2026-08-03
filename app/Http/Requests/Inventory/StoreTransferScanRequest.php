<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'origin_code' => ['nullable', 'string', 'max:100'],
            'logistic_unit_code' => ['nullable', 'string', 'max:100'],
            'logistic_unit_codes' => ['required', 'array', 'min:1'],
            'logistic_unit_codes.*' => ['required', 'string', 'max:100', 'distinct'],
            'destination_code' => ['nullable', 'required_without:destination_location_id', 'string', 'max:100'],
            'destination_location_id' => ['nullable', 'required_without:destination_code', 'integer', 'exists:inventory_locations,id'],
            'material_request_id' => ['required', 'integer', 'exists:inventory_material_requests,id'],
            'transfer_items' => ['nullable', 'array'],
            'transfer_items.*.logistic_unit_code' => ['required_with:transfer_items', 'string', 'max:100'],
            'transfer_items.*.position_id' => ['nullable', 'integer', 'exists:inventory_stock_positions,id'],
            'transfer_items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'fecha_movimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string'],
            'scan_session_uuid' => ['nullable', 'uuid'],
            'device_code' => ['nullable', 'string', 'max:100'],
        ];
    }
}
