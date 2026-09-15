<?php

namespace App\Http\Controllers\Detenciones;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Detenciones\Concerns\AuthorizesDetenciones;
use App\Models\DetencionMotivoCausa;
use App\Models\DetencionMotivoTipo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MotivoController extends Controller
{
    use AuthorizesDetenciones;

    public function index(Request $request)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.motivos.manage');

        $tipos = DetencionMotivoTipo::with(['causas'])->orderBy('codigo')->get()->map(function (DetencionMotivoTipo $tipo) {
            return [
                'id' => $tipo->id,
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'activo' => $tipo->activo,
                'causas' => $tipo->causas->sortBy('codigo')->map(fn (DetencionMotivoCausa $causa) => [
                    'id' => $causa->id,
                    'codigo' => $causa->codigo,
                    'nombre' => $causa->nombre,
                    'activo' => $causa->activo,
                ])->values(),
            ];
        });

        return Inertia::render('Detenciones/Motivos/Index', [
            'tipos' => $tipos,
        ]);
    }

    public function storeTipo(Request $request)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.motivos.manage');

        $request->validate([
            'codigo' => 'required|string|max:50|unique:detenciones_motivo_tipos,codigo',
            'nombre' => 'required|string|max:255',
        ]);

        DetencionMotivoTipo::create([...$request->only(['codigo', 'nombre']), 'activo' => true]);

        return back()->with('success', 'Tipo de motivo creado correctamente.');
    }

    public function updateTipo(Request $request, DetencionMotivoTipo $tipo)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.motivos.manage');

        $request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);

        $tipo->update($request->only(['nombre', 'activo']));

        return back()->with('success', 'Tipo de motivo actualizado correctamente.');
    }

    public function storeCausa(Request $request, DetencionMotivoTipo $tipo)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.motivos.manage');

        $request->validate([
            'codigo' => 'required|string|max:50|unique:detenciones_motivo_causas,codigo',
            'nombre' => 'required|string|max:255',
        ]);

        $tipo->causas()->create([...$request->only(['codigo', 'nombre']), 'activo' => true]);

        return back()->with('success', 'Causa creada correctamente.');
    }

    public function updateCausa(Request $request, DetencionMotivoCausa $causa)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.motivos.manage');

        $request->validate([
            'nombre' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);

        $causa->update($request->only(['nombre', 'activo']));

        return back()->with('success', 'Causa actualizada correctamente.');
    }
}