<?php

namespace App\Http\Controllers\Planning\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\LineCapacity;
use App\Models\PackingLine;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LineCapacityController extends Controller
{
    use AuthorizesPlanning;

    public function index(Request $request)
    {
        $this->authorizePlanning($request);

        $capacities = LineCapacity::query()
            ->with(['packingLine', 'shift'])
            ->orderByDesc('vigencia_desde')
            ->orderByDesc('id')
            ->get();

        $lines = PackingLine::query()->where('activo', true)->orderBy('especie')->orderBy('nombre')->get(['id', 'nombre', 'especie']);
        $shifts = Shift::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'horas']);

        return Inertia::render('Planning/Settings/Capacities', [
            'capacities' => $capacities,
            'lines' => $lines,
            'shifts' => $shifts,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePlanning($request);

        $data = $request->validate([
            'packing_line_id' => ['required', 'integer', 'exists:packing_lines,id'],
            'especie' => ['required', 'string', 'max:80'],
            'bins_por_hora' => ['required', 'numeric', 'min:0.01'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'vigencia_desde' => ['required', 'date'],
            'vigencia_hasta' => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'activo' => ['boolean'],
        ]);

        LineCapacity::create([
            ...$data,
            'activo' => (bool) ($data['activo'] ?? true),
        ]);

        return back()->with('success', 'Capacidad creada.');
    }

    public function update(Request $request, LineCapacity $lineCapacity)
    {
        $this->authorizePlanning($request);

        $data = $request->validate([
            'packing_line_id' => ['required', 'integer', 'exists:packing_lines,id'],
            'especie' => ['required', 'string', 'max:80'],
            'bins_por_hora' => ['required', 'numeric', 'min:0.01'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'vigencia_desde' => ['required', 'date'],
            'vigencia_hasta' => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'activo' => ['boolean'],
        ]);

        $lineCapacity->forceFill([
            ...$data,
            'activo' => (bool) ($data['activo'] ?? true),
        ])->save();

        return back()->with('success', 'Capacidad actualizada.');
    }
}

