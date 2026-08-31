<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Models\PreCoolingAtributo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AtributoController extends Controller
{
    use AuthorizesPreCooling;

    public function index(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        return Inertia::render('PreCooling/Atributos/Index', [
            'atributos' => PreCoolingAtributo::orderBy('codigo')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $data = $this->validar($request);

        PreCoolingAtributo::create([...$data, 'activo' => true]);

        return back()->with('success', 'Atributo creado correctamente.');
    }

    public function update(Request $request, PreCoolingAtributo $atributo)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $data = $this->validar($request, $atributo);
        $data['activo'] = $request->boolean('activo');

        $atributo->update($data);

        return back()->with('success', 'Atributo actualizado.');
    }

    protected function validar(Request $request, ?PreCoolingAtributo $atributo = null): array
    {
        $uniqueRule = $atributo
            ? "unique:pre_cooling_atributos,codigo,{$atributo->id}"
            : 'unique:pre_cooling_atributos,codigo';

        $data = $request->validate([
            'codigo' => "required|string|max:50|{$uniqueRule}",
            'nombre' => 'required|string|max:255',
            'tipo_dato' => 'required|in:texto,numero,fecha,select',
            'opciones' => 'nullable|string',
            'requerido' => 'boolean',
        ]);

        $data['requerido'] = $request->boolean('requerido');

        if ($data['tipo_dato'] === 'select') {
            $raw = $data['opciones'] ?? '';
            $opciones = [];

            if (is_array($raw)) {
                $opciones = $raw;
            } elseif (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $opciones = $decoded;
                } else {
                    $opciones = array_map('trim', explode(',', $raw));
                }
            }

            $opciones = array_values(array_filter($opciones));

            if (empty($opciones)) {
                throw ValidationException::withMessages([
                    'opciones' => 'Debe indicar al menos una opción para el tipo "select".',
                ]);
            }

            $data['opciones'] = $opciones;
        } else {
            $data['opciones'] = null;
        }

        return $data;
    }
}
