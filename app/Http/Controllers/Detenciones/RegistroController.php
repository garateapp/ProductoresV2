<?php

namespace App\Http\Controllers\Detenciones;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Detenciones\Concerns\AuthorizesDetenciones;
use App\Models\DetencionMaquina;
use App\Models\DetencionMotivoTipo;
use App\Models\DetencionRegistro;
use App\Models\DetencionTurno;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RegistroController extends Controller
{
    use AuthorizesDetenciones;

    public function index(Request $request)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.registros.manage');

        $turnos = DetencionTurno::with(['maquina', 'registros.causa.tipo', 'usuarioCreated'])
            ->when($request->filled('maquina_id'), fn ($q) => $q->where('maquina_id', $request->integer('maquina_id')))
            ->when($request->filled('fecha_desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->input('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->input('fecha_hasta')))
            ->orderByDesc('hora_inicio_turno')
            ->take(100)
            ->get()
            ->map(fn (DetencionTurno $turno) => $this->serializarTurno($turno));

        return Inertia::render('Detenciones/Registros/Index', $this->registroProps($request, $turnos));
    }

    public function storeTurno(Request $request)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.registros.manage');

        $request->validate([
            'maquina_id' => 'required|integer|exists:detenciones_maquinas,id',
            'fecha' => 'required|date',
            'hora_inicio_turno' => 'required',
            'operador' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        $horaInicio = $this->parseFechaHora($request->input('hora_inicio_turno'), $request->input('fecha'));

        $abierto = DetencionTurno::where('maquina_id', $request->integer('maquina_id'))
            ->whereNull('hora_fin_turno')
            ->exists();

        if ($abierto) {
            throw ValidationException::withMessages([
                'maquina_id' => 'La máquina seleccionada ya tiene un turno en curso, debes cerrarlo antes de iniciar otro.',
            ]);
        }

        DetencionTurno::create([
            'maquina_id' => $request->integer('maquina_id'),
            'fecha' => Carbon::parse($request->input('fecha'))->startOfDay(),
            'hora_inicio_turno' => $horaInicio,
            'operador' => $request->input('operador'),
            'observaciones' => $request->input('observaciones'),
            'usuario_created_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Turno iniciado correctamente.');
    }

    public function closeTurno(Request $request, DetencionTurno $turno)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.registros.manage');

        $request->validate(['hora_fin_turno' => 'required']);

        $horaFin = $this->parseFechaHora($request->input('hora_fin_turno'), $turno->fecha->format('Y-m-d'));

        if ($turno->estaCerrado()) {
            throw ValidationException::withMessages([
                'hora_fin_turno' => 'El turno ya está cerrado.',
            ]);
        }

        if ($horaFin->lessThanOrEqualTo($turno->hora_inicio_turno)) {
            throw ValidationException::withMessages([
                'hora_fin_turno' => 'La hora de fin debe ser posterior a la hora de inicio del turno.',
            ]);
        }

        $turno->update(['hora_fin_turno' => $horaFin]);

        return back()->with('success', 'Turno cerrado correctamente.');
    }

    public function storeDetencion(Request $request, DetencionTurno $turno)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.registros.manage');

        $request->validate([
            'motivo_causa_id' => 'required|integer|exists:detenciones_motivo_causas,id',
            'hora_detencion' => 'required',
            'hora_reinicio' => 'nullable',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        if ($turno->estaCerrado()) {
            throw ValidationException::withMessages([
                'hora_detencion' => 'No puedes registrar detenciones en un turno cerrado.',
            ]);
        }

        $horaDetencion = $this->parseFechaHora($request->input('hora_detencion'), $turno->fecha->format('Y-m-d'));

        if ($horaDetencion->lessThan($turno->hora_inicio_turno)) {
            throw ValidationException::withMessages([
                'hora_detencion' => 'La hora de detención no puede ser anterior al inicio del turno.',
            ]);
        }

        $horaReinicio = $request->filled('hora_reinicio')
            ? $this->parseFechaHora($request->input('hora_reinicio'), $turno->fecha->format('Y-m-d'))
            : null;

        if ($horaReinicio && $horaReinicio->lessThanOrEqualTo($horaDetencion)) {
            throw ValidationException::withMessages([
                'hora_reinicio' => 'La hora de reinicio debe ser posterior a la hora de detención.',
            ]);
        }

        $turno->registros()->create([
            'motivo_causa_id' => $request->integer('motivo_causa_id'),
            'hora_detencion' => $horaDetencion,
            'hora_reinicio' => $horaReinicio,
            'observaciones' => $request->input('observaciones'),
            'usuario_created_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Detención registrada correctamente.');
    }

    public function updateDetencion(Request $request, DetencionRegistro $detencion)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.registros.manage');

        $request->validate([
            'motivo_causa_id' => 'required|integer|exists:detenciones_motivo_causas,id',
            'hora_detencion' => 'required',
            'hora_reinicio' => 'nullable',
            'observaciones' => 'nullable|string|max:2000',
        ]);

        $turno = $detencion->turno;

        if ($turno->estaCerrado()) {
            throw ValidationException::withMessages([
                'hora_detencion' => 'No puedes editar detenciones de un turno cerrado.',
            ]);
        }

        $horaDetencion = $this->parseFechaHora($request->input('hora_detencion'), $turno->fecha->format('Y-m-d'));
        $horaReinicio = $request->filled('hora_reinicio')
            ? $this->parseFechaHora($request->input('hora_reinicio'), $turno->fecha->format('Y-m-d'))
            : null;

        if ($horaReinicio && $horaReinicio->lessThanOrEqualTo($horaDetencion)) {
            throw ValidationException::withMessages([
                'hora_reinicio' => 'La hora de reinicio debe ser posterior a la hora de detención.',
            ]);
        }

        $detencion->update([
            'motivo_causa_id' => $request->integer('motivo_causa_id'),
            'hora_detencion' => $horaDetencion,
            'hora_reinicio' => $horaReinicio,
            'observaciones' => $request->input('observaciones'),
        ]);

        return back()->with('success', 'Detención actualizada correctamente.');
    }

    public function destroyDetencion(Request $request, DetencionRegistro $detencion)
    {
        $this->authorizeDetencionesPermission($request, 'detenciones.registros.manage');

        if ($detencion->turno->estaCerrado()) {
            throw ValidationException::withMessages([
                'turno_id' => 'No puedes eliminar detenciones de un turno cerrado.',
            ]);
        }

        $detencion->delete();

        return back()->with('success', 'Detención eliminada correctamente.');
    }

    protected function registroProps(Request $request, $turnos): array
    {
        $maquinas = DetencionMaquina::where('activo', true)->orderBy('codigo')->get()->map(fn (DetencionMaquina $m) => [
            'id' => $m->id,
            'codigo' => $m->codigo,
            'nombre' => $m->nombre,
        ]);

        $motivos = DetencionMotivoTipo::with('causasActivas')
            ->where('activo', true)
            ->orderBy('codigo')
            ->get()
            ->map(fn (DetencionMotivoTipo $tipo) => [
                'id' => $tipo->id,
                'codigo' => $tipo->codigo,
                'nombre' => $tipo->nombre,
                'causas' => $tipo->causasActivas->map(fn ($causa) => [
                    'id' => $causa->id,
                    'codigo' => $causa->codigo,
                    'nombre' => $causa->nombre,
                ])->values(),
            ]);

        $filtrosMaquinas = DetencionMaquina::orderBy('codigo')->get()->map(fn (DetencionMaquina $m) => [
            'id' => $m->id,
            'codigo' => $m->codigo,
            'nombre' => $m->nombre,
        ]);

        return [
            'turnos' => $turnos,
            'maquinas' => $maquinas,
            'motivos' => $motivos,
            'filtros' => [
                'maquinas' => $filtrosMaquinas,
                'fecha_desde' => $request->input('fecha_desde', ''),
                'fecha_hasta' => $request->input('fecha_hasta', ''),
            ],
        ];
    }

    protected function serializarTurno(DetencionTurno $turno): array
    {
        return [
            'id' => $turno->id,
            'fecha' => $turno->fecha->format('Y-m-d'),
            'hora_inicio_turno' => $turno->hora_inicio_turno->format('Y-m-d H:i'),
            'hora_fin_turno' => $turno->hora_fin_turno?->format('Y-m-d H:i'),
            'operador' => $turno->operador,
            'observaciones' => $turno->observaciones,
            'cerrado' => $turno->estaCerrado(),
            'minutos_trabajados' => $turno->minutosTrabajados(),
            'maquina' => $turno->maquina ? [
                'id' => $turno->maquina->id,
                'codigo' => $turno->maquina->codigo,
                'nombre' => $turno->maquina->nombre,
            ] : null,
            'usuario' => $turno->usuarioCreated?->name,
            'registros' => $turno->registros->sortBy('hora_detencion')->map(fn (DetencionRegistro $r) => [
                'id' => $r->id,
                'motivo_causa_id' => $r->motivo_causa_id,
                'tipo' => $r->causa?->tipo ? [
                    'id' => $r->causa->tipo->id,
                    'nombre' => $r->causa->tipo->nombre,
                ] : null,
                'causa' => $r->causa ? [
                    'id' => $r->causa->id,
                    'nombre' => $r->causa->nombre,
                ] : null,
                'hora_detencion' => $r->hora_detencion->format('Y-m-d H:i'),
                'hora_reinicio' => $r->hora_reinicio?->format('Y-m-d H:i'),
                'en_curso' => $r->estaEnCurso(),
                'minutos' => $r->minutosPerdidos(),
                'observaciones' => $r->observaciones,
            ])->values(),
        ];
    }

    protected function parseFechaHora(string $value, string $fecha): Carbon
    {
        return Carbon::parse(str_replace('T', ' ', $value));
    }
}