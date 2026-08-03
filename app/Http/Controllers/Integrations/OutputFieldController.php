<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationProfile;
use App\Models\IntegrationOutputField;
use App\Enums\IntegrationFieldType;
use Illuminate\Http\Request;

class OutputFieldController extends Controller
{
    public function store(Request $request, IntegrationProfile $profile)
    {
        $version = $profile->currentVersion;
        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden modificar campos en una versión inmutable.');
        }

        $data = $request->validate([
            'clave_externa' => "required|string|max:100|unique:integration_output_fields,clave_externa,null,null,profile_version_id,{$version->id}",
            'etiqueta' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'tipo_dato' => 'required|string|in:' . implode(',', collect(IntegrationFieldType::cases())->pluck('value')->toArray()),
            'obligatorio' => 'boolean',
            'permite_nulo' => 'boolean',
            'valor_defecto' => 'nullable|string|max:500',
            'largo_maximo' => 'nullable|integer|min:0',
            'precision' => 'nullable|integer|min:0',
            'escala_decimal' => 'nullable|integer|min:0',
            'mascara_formato' => 'nullable|string|max:100',
            'posicion' => 'integer|min:0',
        ]);

        $data['profile_version_id'] = $version->id;
        $data['activo'] = true;

        $field = IntegrationOutputField::create($data);

        return back()->with('success', 'Campo de salida agregado.');
    }

    public function update(Request $request, IntegrationProfile $profile, IntegrationOutputField $outputField)
    {
        $version = $profile->currentVersion;
        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden modificar campos en una versión inmutable.');
        }

        $data = $request->validate([
            'clave_externa' => "required|string|max:100|unique:integration_output_fields,clave_externa,{$outputField->id},id,profile_version_id,{$version->id}",
            'etiqueta' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'tipo_dato' => 'required|string|in:' . implode(',', collect(IntegrationFieldType::cases())->pluck('value')->toArray()),
            'obligatorio' => 'boolean',
            'permite_nulo' => 'boolean',
            'valor_defecto' => 'nullable|string|max:500',
            'largo_maximo' => 'nullable|integer|min:0',
            'precision' => 'nullable|integer|min:0',
            'escala_decimal' => 'nullable|integer|min:0',
            'mascara_formato' => 'nullable|string|max:100',
            'posicion' => 'integer|min:0',
            'activo' => 'boolean',
        ]);

        $outputField->update($data);

        return back()->with('success', 'Campo de salida actualizado.');
    }

    public function destroy(IntegrationProfile $profile, IntegrationOutputField $outputField)
    {
        $version = $profile->currentVersion;
        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden eliminar campos en una versión inmutable.');
        }

        $outputField->delete();

        return back()->with('success', 'Campo de salida eliminado.');
    }

    public function reorder(Request $request, IntegrationProfile $profile)
    {
        $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'required|exists:integration_output_fields,id',
            'fields.*.posicion' => 'required|integer|min:0',
        ]);

        foreach ($request->fields as $field) {
            IntegrationOutputField::where('id', $field['id'])->update(['posicion' => $field['posicion']]);
        }

        return back()->with('success', 'Posiciones actualizadas.');
    }
}
