<?php

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class StorePackingProcessBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Si viene vacío: se planifica para TODAS las especies disponibles.
            'especie' => ['nullable', 'string', 'max:80'],
            'week_start' => ['required', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'included_packing_line_ids' => ['nullable', 'array'],
            'included_packing_line_ids.*' => ['integer', 'exists:packing_lines,id'],
            'auto_generate' => ['nullable', 'boolean'],
        ];
    }
}
