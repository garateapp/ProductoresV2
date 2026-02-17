<?php

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class StorePackingProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $incoming = $this->input('especies');
        $especies = is_array($incoming) ? $incoming : [];

        if (empty($especies) && $this->filled('especie')) {
            $especies = [(string) $this->input('especie')];
        }

        $especies = collect($especies)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        $merge = [
            'especies' => $especies,
        ];

        if (! empty($especies) && ! $this->filled('especie')) {
            $merge['especie'] = $especies[0];
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'especie' => ['nullable', 'string', 'max:80'],
            'especies' => ['required', 'array', 'min:1'],
            'especies.*' => ['required', 'string', 'max:80', 'distinct', 'exists:especies,name'],
            'planning_mode' => ['nullable', 'string', 'in:normal,reembalaje'],
            'fecha' => ['required', 'date'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'included_packing_line_ids' => ['nullable', 'array'],
            'included_packing_line_ids.*' => ['integer', 'exists:packing_lines,id'],
            'pedidos' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
