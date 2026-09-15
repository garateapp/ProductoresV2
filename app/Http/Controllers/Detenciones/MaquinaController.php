<?php

namespace App\Http\Controllers\Detenciones;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Detenciones\Concerns\AuthorizesDetenciones;
use App\Models\DetencionMaquina;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaquinaController extends Controller
{
    use AuthorizesDetenciones;

    public function index(Request $request)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.maquinas.manage');

        $maquinas = DetencionMaquina::orderBy('codigo')->get()->map(function (DetencionMaquina $maquina) {
            return [
                'id' => $maquina->id,
                'codigo' => $maquina->codigo,
                'nombre' => $maquina->nombre,
                'activo' => $maquina->activo,
                'turnos' => $maquina->turnos()->count(),
            ];
        });

        return Inertia::render('Detenciones/Maquinas/Index', [
            'maquinas' => $maquinas,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.maquinas.manage');

        $data = $request->validate([
            'codigo' => 'required|string|max:50|unique:detenciones_maquinas,codigo',
            'nombre' => 'required|string|max:255',
        ]);

        DetencionMaquina::create([...$data, 'activo' => true]);

        return back()->with('success', 'Máquina creada correctamente.');
    }

    public function update(Request $request, DetencionMaquina $maquina)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.maquinas.manage');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);

        $maquina->update($data);

        return back()->with('success', 'Máquina actualizada correctamente.');
    }
}