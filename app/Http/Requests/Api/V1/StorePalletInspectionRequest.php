<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePalletInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folio' => ['required', 'string'],
            // 'c_embalaje' => ['required', 'string'],
            'technical_sheet_id' => ['required', 'exists:inventory_technical_sheets,id'],
            'observaciones_generales' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.technical_sheet_pallet_item_id' => ['required', 'exists:inventory_technical_sheet_pallet_items,id'],
            'items.*.material_utilizado_id' => ['required', 'exists:inventory_materials,id'],
            'items.*.es_reemplazo' => ['required', 'boolean'],
            'items.*.cumple' => ['required', 'boolean'],
            'items.*.motivo_no_cumple' => ['required_if:items.*.cumple,false', 'nullable', 'string', 'max:255'],
        ];
    }
}
