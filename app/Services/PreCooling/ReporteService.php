<?php

namespace App\Services\PreCooling;

use App\Models\PreCoolingCamara;
use App\Models\PreCoolingLoad;
use App\Models\PreCoolingLoadFolio;
use App\Models\PreCoolingSaldo;
use App\Models\PreCoolingTunel;

class ReporteService
{
    public function estadoTuneles(): array
    {
        return PreCoolingTunel::with(['cargas.folios', 'parametros'])
            ->orderBy('codigo')
            ->get()
            ->map(function (PreCoolingTunel $tunel) {
                $activos = $tunel->parametros->where('activo', true);

                $capacidad = $activos->where('dimension', 'banda')->count()
                    * $activos->where('dimension', 'posicion')->count()
                    * $activos->where('dimension', 'altura')->count();

                $cargas = $tunel->cargas;

                return [
                    'id' => $tunel->id,
                    'codigo' => $tunel->codigo,
                    'nombre' => $tunel->nombre,
                    'activo' => $tunel->activo,
                    'capacidad' => $capacidad,
                    'total' => $cargas->count(),
                    'ingresado' => $cargas->where('estado', 'ingresado')->count(),
                    'iniciado' => $cargas->where('estado', 'iniciado')->count(),
                    'salido' => $cargas->where('estado', 'salido')->count(),
                    'cajas' => $cargas->sum(fn ($c) => $c->folios->sum('cajas')),
                ];
            })
            ->values()
            ->all();
    }

    public function saldosCamaras(): array
    {
        $saldos = PreCoolingSaldo::with(['camara', 'tipoProceso'])
            ->orderBy('camara_id')
            ->orderBy('banda')
            ->orderBy('fila')
            ->orderBy('columna')
            ->orderBy('altura')
            ->orderBy('nivel')
            ->get();

        return $saldos
            ->groupBy('camara_id')
            ->map(function ($items, $camaraId) {
                $camara = $items->first()->camara;

                return [
                    'camara_id' => (int) $camaraId,
                    'codigo' => $camara->codigo,
                    'nombre' => $camara->nombre,
                    'total' => $items->count(),
                    'cajas' => $items->sum('cajas'),
                    'pallets' => $items->sum('pallets'),
                    'saldos' => $items->map(fn ($s) => [
                        'banda' => $s->banda,
                        'fila' => $s->fila,
                        'columna' => $s->columna,
                        'altura' => $s->altura,
                        'nivel' => $s->nivel,
                        'folio' => $s->folio,
                        'tipo_proceso' => $s->tipoProceso?->codigo,
                        'cajas' => $s->cajas,
                        'especie' => $s->especie,
                        'variedad' => $s->variedad,
                        'productor' => $s->productor,
                    ])->values(),
                ];
            })
            ->values()
            ->all();
    }

    public function resumen(): array
    {
        $tuneles = PreCoolingTunel::all();

        return [
            'tuneles' => [
                'total' => $tuneles->count(),
                'activos' => $tuneles->where('activo', true)->count(),
                'con_carga_activa' => PreCoolingLoad::whereIn('estado', ['ingresado', 'iniciado'])
                    ->distinct()
                    ->count('tunel_id'),
            ],
            'camaras' => [
                'total' => PreCoolingCamara::count(),
                'activas' => PreCoolingCamara::where('activo', true)->count(),
            ],
            'cargas' => [
                'total' => PreCoolingLoad::count(),
                'ingresado' => PreCoolingLoad::where('estado', 'ingresado')->count(),
                'iniciado' => PreCoolingLoad::where('estado', 'iniciado')->count(),
                'salido' => PreCoolingLoad::where('estado', 'salido')->count(),
                'folios' => PreCoolingLoadFolio::count(),
            ],
            'saldos' => [
                'total' => PreCoolingSaldo::count(),
                'cajas' => (int) PreCoolingSaldo::sum('cajas'),
                'pallets' => (int) PreCoolingSaldo::sum('pallets'),
            ],
        ];
    }
}
