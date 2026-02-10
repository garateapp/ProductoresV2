<?php

namespace App\Http\Controllers\Planning\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends Controller
{
    use AuthorizesPlanning;

    public function index(Request $request)
    {
        $this->authorizePlanning($request);

        $shifts = Shift::query()->orderBy('codigo')->get();

        return Inertia::render('Planning/Settings/Shifts', [
            'shifts' => $shifts,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePlanning($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:20', 'unique:shifts,codigo'],
            'nombre' => ['required', 'string', 'max:80'],
            'horas' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'activo' => ['boolean'],
        ]);

        Shift::create([
            ...$data,
            'horas' => (float) $data['horas'],
            'hora_inicio' => $data['hora_inicio'] ? ($data['hora_inicio'].':00') : null,
            'activo' => (bool) ($data['activo'] ?? true),
        ]);

        return back()->with('success', 'Turno creado.');
    }

    public function update(Request $request, Shift $shift)
    {
        $this->authorizePlanning($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:20', 'unique:shifts,codigo,'.$shift->id],
            'nombre' => ['required', 'string', 'max:80'],
            'horas' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'activo' => ['boolean'],
        ]);

        $shift->forceFill([
            ...$data,
            'horas' => (float) $data['horas'],
            'hora_inicio' => $data['hora_inicio'] ? ($data['hora_inicio'].':00') : null,
            'activo' => (bool) ($data['activo'] ?? true),
        ])->save();

        return back()->with('success', 'Turno actualizado.');
    }
}
