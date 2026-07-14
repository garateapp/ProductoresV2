<?php

namespace App\Http\Controllers\Planning\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\Especie;
use App\Models\PackingLine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackingLineController extends Controller
{
    use AuthorizesPlanning;

    public function index(Request $request)
    {
        $this->authorizePlanning($request);

        $lines = PackingLine::query()
            ->with(['processLots' => function ($q) {
                $q->with('process')
                    ->whereHas('process', fn ($pq) => $pq->whereIn('estado', ['BORRADOR', 'CONFLICTO', 'CONFIRMADO', 'EN_PROCESO']))
                    ->orderBy('orden');
            }])
            ->orderBy('especie')
            ->orderBy('nombre')
            ->get();

        $especies = Especie::query()->orderBy('name')->pluck('name')->values();

        return Inertia::render('Planning/Settings/Lines', [
            'lines' => $lines,
            'especies' => $especies,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePlanning($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'tipo' => ['required', 'in:AUTOMATIZADA,HAND_PACK'],
            'especies' => ['required', 'array', 'min:1'],
            'especies.*' => ['string', 'max:80'],
            'activo' => ['boolean'],
        ]);

        $especies = collect($data['especies'] ?? [])
            ->filter()
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        PackingLine::create([
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            // legacy: dejamos primera especie para ordenamiento/compatibilidad
            'especie' => $especies[0] ?? '',
            'especies' => $especies,
            'activo' => (bool) ($data['activo'] ?? true),
        ]);

        return back()->with('success', 'Línea/Cámara creada.');
    }

    public function update(Request $request, PackingLine $packingLine)
    {
        $this->authorizePlanning($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'tipo' => ['required', 'in:AUTOMATIZADA,HAND_PACK'],
            'especies' => ['required', 'array', 'min:1'],
            'especies.*' => ['string', 'max:80'],
            'activo' => ['boolean'],
        ]);

        $especies = collect($data['especies'] ?? [])
            ->filter()
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        $packingLine->forceFill([
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'especie' => $especies[0] ?? ($packingLine->especie ?? ''),
            'especies' => $especies,
            'activo' => (bool) ($data['activo'] ?? true),
        ])->save();

        return back()->with('success', 'Línea/Cámara actualizada.');
    }
}
