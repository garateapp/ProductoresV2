<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Models\PreCoolingLoad;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoadsController extends Controller
{
    use AuthorizesPreCooling;

    public function index(Request $request)
    {
        $this->authorizePreCooling($request);

        $query = PreCoolingLoad::with(['tipoProceso', 'tunel', 'camaraDestino'])
            ->orderByDesc('created_at');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('tunel_id')) {
            $query->where('tunel_id', $request->input('tunel_id'));
        }

        $cargas = $query->get()->map(fn ($carga) => [
            'id' => $carga->id,
            'numero' => $carga->numero,
            'estado' => $carga->estado,
            'tipo_proceso' => $carga->tipoProceso?->codigo,
            'tipo_proceso_nombre' => $carga->tipoProceso?->nombre,
            'tunel' => $carga->tunel?->codigo,
            'tunel_nombre' => $carga->tunel?->nombre,
            'camara_destino' => $carga->camaraDestino?->codigo,
            'camara_destino_nombre' => $carga->camaraDestino?->nombre,
            'temperatura_objetivo' => $carga->temperatura_objetivo,
            'atributos' => $carga->atributos,
            'tunel_id' => $carga->tunel_id,
            'fecha_hora_inicio' => $carga->fecha_hora_inicio?->format('Y-m-d H:i'),
            'fecha_hora_inversion' => $carga->fecha_hora_inversion?->format('Y-m-d H:i'),
            'fecha_hora_fin' => $carga->fecha_hora_fin?->format('Y-m-d H:i'),
            'fecha_hora_termino' => $carga->fecha_hora_termino?->format('Y-m-d H:i'),
            'fecha_hora_descarga' => $carga->fecha_hora_descarga?->format('Y-m-d H:i'),
            'folios_count' => $carga->folios()->count(),
            'created_at' => $carga->created_at->format('Y-m-d H:i'),
        ]);

        $tuneles = \App\Models\PreCoolingTunel::orderBy('codigo')->get(['id', 'codigo', 'nombre']);

        return Inertia::render('PreCooling/Loads/Index', [
            'cargas' => $cargas,
            'tuneles' => $tuneles,
        ]);
    }
}
