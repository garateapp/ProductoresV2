<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Models\PreCoolingTipoProceso;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TipoProcesoController extends Controller
{
    use AuthorizesPreCooling;

    public function index(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        return Inertia::render('PreCooling/TiposProcesos/Index', [
            'tiposProcesos' => PreCoolingTipoProceso::orderBy('codigo')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $data = $request->validate([
            'codigo' => 'required|string|max:50|unique:pre_cooling_tipos_procesos,codigo',
            'nombre' => 'required|string|max:255',
            'tiempo_objetivo_minutos' => 'nullable|integer|min:0',
        ]);

        PreCoolingTipoProceso::create([...$data, 'activo' => true]);

        return back()->with('success', 'Tipo de proceso creado correctamente.');
    }

    public function update(Request $request, PreCoolingTipoProceso $tipoProceso)
    {
        $this->authorizePreCoolingPermission($request, 'prefrio.manage');

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'tiempo_objetivo_minutos' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);

        $tipoProceso->update($data);

        return back()->with('success', 'Tipo de proceso actualizado.');
    }
}
