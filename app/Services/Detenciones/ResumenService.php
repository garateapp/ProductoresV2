<?php

namespace App\Services\Detenciones;

use App\Models\DetencionMaquina;
use App\Models\DetencionRegistro;
use App\Models\DetencionTurno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ResumenService
{
    public function resumen(?string $desde = null, ?string $hasta = null): array
    {
        $from = $desde ? Carbon::parse($desde)->startOfDay() : Carbon::now()->startOfDay()->subDays(13);
        $to = $hasta ? Carbon::parse($hasta)->endOfDay() : Carbon::now()->endOfDay();

        $registros = DetencionRegistro::with(['causa.tipo', 'turno.maquina'])
            ->whereBetween('hora_detencion', [$from, $to])
            ->get();

        $turnos = DetencionTurno::with('registros')
            ->whereBetween('hora_fin_turno', [$from, $to])
            ->whereNotNull('hora_fin_turno')
            ->get();

        [$minutosPerdidos, $minutosEnCurso] = $this->minutosTotales($registros);
        [$trabajados, $brutos] = $this->minutosTurnos($turnos);

        return [
            'fechaDesde' => $from->format('Y-m-d'),
            'fechaHasta' => $to->format('Y-m-d'),
            'kpis' => [
                'detenciones' => $registros->count(),
                'enCurso' => $registros->whereNull('hora_reinicio')->count(),
                'minutosPerdidos' => $minutosPerdidos,
                'minutosPerdidosFormatted' => $this->formatearMinutos($minutosPerdidos),
                'minutosEnCurso' => $minutosEnCurso,
                'turnosCerrados' => $turnos->count(),
                'disponibilidad' => $brutos > 0 ? round(($trabajados / $brutos) * 100, 1) : null,
                'maquinaAfectada' => $this->maquinaMasAfectada($registros),
            ],
            'charts' => [
                'porMaquina' => $this->porMaquina($registros),
                'porTipo' => $this->porTipo($registros),
                'tendencia' => $this->tendencia($registros, $from, $to),
                'topCausas' => $this->topCausas($registros),
            ],
            'maquinas' => $this->estadoMaquinas($registros),
        ];
    }

    protected function minutosTotales(Collection $registros): array
    {
        $perdidos = $registros
            ->filter(fn (DetencionRegistro $r) => $r->hora_reinicio !== null)
            ->sum(fn (DetencionRegistro $r) => $r->minutosPerdidos());
        $enCurso = $registros
            ->filter(fn (DetencionRegistro $r) => $r->hora_reinicio === null)
            ->count();

        return [(int) $perdidos, $enCurso];
    }

    protected function minutosTurnos(Collection $turnos): array
    {
        if ($turnos->isEmpty()) {
            return [0, 0];
        }

        $brutos = $turnos->sum(fn (DetencionTurno $t) => (int) $t->hora_inicio_turno->diffInMinutes($t->hora_fin_turno));
        $trabajados = $turnos->sum(fn (DetencionTurno $t) => (int) $t->minutosTrabajados());

        return [$trabajados, $brutos];
    }

    protected function maquinaMasAfectada(Collection $registros): ?array
    {
        $porMaquina = $registros->groupBy(fn (DetencionRegistro $r) => $r->turno->maquina_id)
            ->map(function (Collection $items) {
                $maquina = $items->first()->turno->maquina;
                $minutos = (int) $items->sum(fn (DetencionRegistro $r) => $r->minutosPerdidos());

                return [
                    'id' => $maquina->id,
                    'codigo' => $maquina->codigo,
                    'nombre' => $maquina->nombre,
                    'detenciones' => $items->count(),
                    'minutos' => $minutos,
                ];
            })
            ->sortByDesc('minutos')
            ->first();

        return $porMaquina;
    }

    protected function porMaquina(Collection $registros): array
    {
        $agrupado = $registros->groupBy(fn (DetencionRegistro $r) => $r->turno->maquina->nombre)
            ->map(function (Collection $items) {
                return [
                    'detenciones' => $items->count(),
                    'minutos' => (int) $items->sum(fn (DetencionRegistro $r) => $r->minutosPerdidos()),
                ];
            })
            ->sortByDesc('minutos');

        return [
            'labels' => $agrupado->keys()->values()->all(),
            'detenciones' => $agrupado->pluck('detenciones')->values()->all(),
            'minutos' => $agrupado->pluck('minutos')->values()->all(),
        ];
    }

    protected function porTipo(Collection $registros): array
    {
        $agrupado = $registros->groupBy(fn (DetencionRegistro $r) => $r->causa->tipo->nombre)
            ->map(fn (Collection $items) => (int) $items->sum(fn (DetencionRegistro $r) => $r->minutosPerdidos()))
            ->sortByDesc(fn ($value) => $value)
            ->filter(fn ($value) => $value > 0);

        return [
            'labels' => $agrupado->keys()->values()->all(),
            'series' => $agrupado->values()->all(),
        ];
    }

    protected function tendencia(Collection $registros, Carbon $from, Carbon $to): array
    {
        $dias = new Collection();
        for ($date = $from->copy(); $date->lessThanOrEqualTo($to); $date->addDay()) {
            $dias->push($date->copy());
        }

        $porDia = $registros->groupBy(fn (DetencionRegistro $r) => $r->hora_detencion->format('Y-m-d'))
            ->map(fn (Collection $items) => $items->count());

        return [
            'categories' => $dias->map(fn (Carbon $d) => $d->format('d/m'))->all(),
            'series' => $dias->map(fn (Carbon $d) => $porDia->get($d->format('Y-m-d'), 0))->all(),
        ];
    }

    protected function topCausas(Collection $registros): array
    {
        $agrupado = $registros->groupBy(fn (DetencionRegistro $r) => $r->causa->tipo->nombre.' / '.$r->causa->nombre)
            ->map(fn (Collection $items) => (int) $items->sum(fn (DetencionRegistro $r) => $r->minutosPerdidos()))
            ->sortByDesc(fn ($value) => $value)
            ->filter(fn ($value) => $value > 0)
            ->take(10);

        return [
            'labels' => $agrupado->keys()->reverse()->values()->all(),
            'series' => $agrupado->reverse()->values()->all(),
        ];
    }

    protected function estadoMaquinas(Collection $registros): array
    {
        return DetencionMaquina::withCount('turnos')
            ->orderBy('codigo')
            ->get()
            ->map(function (DetencionMaquina $maquina) use ($registros) {
                $items = $registros->filter(fn (DetencionRegistro $r) => $r->turno->maquina_id === $maquina->id);
                $minutos = (int) $items->sum(fn (DetencionRegistro $r) => $r->minutosPerdidos());

                return [
                    'id' => $maquina->id,
                    'codigo' => $maquina->codigo,
                    'nombre' => $maquina->nombre,
                    'activo' => $maquina->activo,
                    'detenciones' => $items->count(),
                    'enCurso' => $items->whereNull('hora_reinicio')->count(),
                    'minutos' => $minutos,
                ];
            })
            ->values()
            ->all();
    }

    protected function formatearMinutos(int $minutos): string
    {
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
}