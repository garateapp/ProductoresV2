<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationProfile;
use App\Models\IntegrationInputField;
use App\Enums\IntegrationFieldType;
use Illuminate\Http\Request;

class InputFieldController extends Controller
{
    public function store(Request $request, IntegrationProfile $profile)
    {
        $version = $profile->currentVersion;
        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden modificar campos en una versión inmutable.');
        }

        $data = $request->validate([
            'clave' => "required|string|max:100|unique:integration_input_fields,clave,null,null,profile_version_id,{$version->id}",
            'etiqueta' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'tipo_dato' => 'required|string|in:' . implode(',', collect(IntegrationFieldType::cases())->pluck('value')->toArray()),
            'ruta_valor' => 'nullable|string|max:200',
            'obligatorio' => 'boolean',
            'permite_nulo' => 'boolean',
            'valor_ejemplo' => 'nullable|string|max:500',
            'posicion' => 'integer|min:0',
            'config_adicional' => 'nullable|array',
        ]);

        $data['profile_version_id'] = $version->id;
        $data['activo'] = true;

        $field = IntegrationInputField::create($data);

        return back()->with('success', 'Campo de entrada agregado.');
    }

    public function update(Request $request, IntegrationProfile $profile, IntegrationInputField $inputField)
    {
        $version = $profile->currentVersion;
        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden modificar campos en una versión inmutable.');
        }

        $data = $request->validate([
            'clave' => "required|string|max:100|unique:integration_input_fields,clave,{$inputField->id},id,profile_version_id,{$version->id}",
            'etiqueta' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'tipo_dato' => 'required|string|in:' . implode(',', collect(IntegrationFieldType::cases())->pluck('value')->toArray()),
            'ruta_valor' => 'nullable|string|max:200',
            'obligatorio' => 'boolean',
            'permite_nulo' => 'boolean',
            'valor_ejemplo' => 'nullable|string|max:500',
            'posicion' => 'integer|min:0',
            'activo' => 'boolean',
            'config_adicional' => 'nullable|array',
        ]);

        $inputField->update($data);

        return back()->with('success', 'Campo de entrada actualizado.');
    }

    public function destroy(IntegrationProfile $profile, IntegrationInputField $inputField)
    {
        $version = $profile->currentVersion;
        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden eliminar campos en una versión inmutable.');
        }

        $inputField->delete();

        return back()->with('success', 'Campo de entrada eliminado.');
    }

    public function reorder(Request $request, IntegrationProfile $profile)
    {
        $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'required|exists:integration_input_fields,id',
            'fields.*.posicion' => 'required|integer|min:0',
        ]);

        foreach ($request->fields as $field) {
            IntegrationInputField::where('id', $field['id'])->update(['posicion' => $field['posicion']]);
        }

        return back()->with('success', 'Posiciones actualizadas.');
    }
}
