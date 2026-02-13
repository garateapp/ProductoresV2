<?php

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class StorePackingProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'especie' => ['required', 'string', 'max:80'],
            'planning_mode' => ['nullable', 'string', 'in:normal,reembalaje'],
            'fecha' => ['required', 'date'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'included_packing_line_ids' => ['nullable', 'array'],
            'included_packing_line_ids.*' => ['integer', 'exists:packing_lines,id'],
            'pedidos' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
