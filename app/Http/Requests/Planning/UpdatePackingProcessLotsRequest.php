<?php

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackingProcessLotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Motivo general (obligatorio si se edita un proceso confirmado: queda en versionado del instructivo).
            'change_reason' => ['nullable', 'string', 'max:2000'],

            // Pedidos / observaciones del proceso (texto libre).
            'pedidos' => ['nullable', 'string', 'max:2000'],

            // Horas extra (overtime) por línea/cámara (aplica a capacidad y horarios estimados).
            'line_extra_hours' => ['nullable', 'array'],
            'line_extra_hours.*.packing_line_id' => ['required_with:line_extra_hours', 'integer', 'exists:packing_lines,id'],
            'line_extra_hours.*.extra_horas' => ['required_with:line_extra_hours', 'numeric', 'min:0', 'max:24'],

            // Líneas incluidas en el proceso (permite operar en 1 o más líneas/cámaras).
            'included_packing_line_ids' => ['nullable', 'array', 'min:1'],
            'included_packing_line_ids.*' => ['integer', 'exists:packing_lines,id'],

            // Actualización masiva de orden/linea/embalaje (simple para operación en planta).
            'lots' => ['nullable', 'array'],
            'lots.*.id' => ['required_with:lots', 'integer', 'exists:process_lots,id'],
            'lots.*.packing_line_id' => ['required_with:lots', 'integer', 'exists:packing_lines,id'],
            'lots.*.orden' => ['required_with:lots', 'integer', 'min:1'],
            'lots.*.destino' => ['nullable', 'string', 'max:60'],
            'lots.*.c_embalaje' => ['nullable', 'string', 'max:60'],
            'lots.*.n_embalaje' => ['nullable', 'string', 'max:160'],
            'lots.*.cp2_cajas_por_pallet' => ['nullable', 'integer', 'min:1'],
            'lots.*.packaging_change_reason' => ['nullable', 'string', 'max:500'],
            'lots.*.packaging_indications' => ['nullable', 'string', 'max:1000'],
            'lots.*.extra_packagings' => ['nullable', 'array'],
            'lots.*.extra_packagings.*.c_embalaje' => ['nullable', 'string', 'max:60'],
            'lots.*.extra_packagings.*.n_embalaje' => ['nullable', 'string', 'max:160'],
            'lots.*.extra_packagings.*.cp2_cajas_por_pallet' => ['nullable', 'integer', 'min:1'],
            'lots.*.extra_packagings.*.indications' => ['nullable', 'string', 'max:1000'],

            // Eliminar lotes
            'remove_ids' => ['nullable', 'array'],
            'remove_ids.*' => ['integer', 'exists:process_lots,id'],

            // Agregar lote desde inventario (por n_g_recepcion) a una línea
            'add_n_g_recepcion' => ['nullable', 'string', 'max:64'],
            // Reembalaje: agregar desde inventario por clave de origen (folio).
            'add_source_type' => ['nullable', 'string', 'in:recepcion,reembalaje'],
            'add_source_key' => ['nullable', 'string', 'max:120'],
            'add_packing_line_id' => ['required_with:add_n_g_recepcion,add_source_key', 'integer', 'exists:packing_lines,id'],

            // Split: partir un lote y asignarlo a otra línea/cámara
            'split_id' => ['nullable', 'integer', 'exists:process_lots,id'],
            'split_bins' => ['required_with:split_id', 'integer', 'min:1'],
            'split_to_packing_line_id' => ['required_with:split_id', 'integer', 'exists:packing_lines,id'],
        ];
    }
}
