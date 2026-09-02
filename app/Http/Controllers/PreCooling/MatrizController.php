<?php

namespace App\Http\Controllers\PreCooling;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PreCooling\Concerns\AuthorizesPreCooling;
use App\Models\PreCoolingAtributo;
use App\Models\PreCoolingCamara;
use App\Models\PreCoolingLoad;
use App\Models\PreCoolingSaldo;
use App\Models\PreCoolingTipoProceso;
use App\Models\PreCoolingTunel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MatrizController extends Controller
{
    use AuthorizesPreCooling;

    public function tunel(Request $request)
    {
        $this->authorizePreCooling($request);

        $tuneles = PreCoolingTunel::orderBy('codigo')->get();

        $tunelId = (int) $request->query('tunel_id', 0);
        $tunel = $tunelId ? $tuneles->firstWhere('id', $tunelId) : null;

        $parametros = [];
        $cargaActiva = null;
        $folios = [];

        if ($tunel) {
            $tunel->load('parametros');
            $parametros = $tunel->parametrosPorDimension();

            $carga = PreCoolingLoad::with(['folios.tipoProceso', 'tipoProceso'])
                ->where('tunel_id', $tunel->id)
                ->whereIn('estado', ['ingresado', 'iniciado'])
                ->first();

            if ($carga) {
                $cargaActiva = [
                    'id' => $carga->id,
                    'numero' => $carga->numero,
                    'tipo_proceso_id' => $carga->tipo_proceso_id,
                    'tipo_proceso' => $carga->tipoProceso?->codigo,
                    'tipo_proceso_nombre' => $carga->tipoProceso?->nombre,
                    'tunel_id' => $carga->tunel_id,
                    'camara_destino_id' => $carga->camara_destino_id,
                    'estado' => $carga->estado,
                    'fecha_hora_inicio' => $carga->fecha_hora_inicio?->format('Y-m-d\TH:i'),
                    'fecha_hora_inversion' => $carga->fecha_hora_inversion?->format('Y-m-d\TH:i'),
                    'fecha_hora_termino' => $carga->fecha_hora_termino?->format('Y-m-d\TH:i'),
                    'fecha_hora_descarga' => $carga->fecha_hora_descarga?->format('Y-m-d\TH:i'),
                    'temperatura_objetivo' => $carga->temperatura_objetivo,
                    'temperatura_ambiente_inicio' => $carga->temperatura_ambiente_inicio,
                    'temperatura_ambiente_inversion' => $carga->temperatura_ambiente_inversion,
                    'temperatura_ambiente_final' => $carga->temperatura_ambiente_final,
                    'folios_total' => $carga->folios->count(),
                    'folios_salidos' => $carga->folios->whereNotNull('fecha_hora_salida')->count(),
                    'folios_pendientes' => $carga->folios->whereNull('fecha_hora_salida')->count(),
                    'observaciones' => $carga->observaciones,
                    'atributos' => $carga->atributos,
                ];

                $folios = $carga->folios->whereNull('fecha_hora_salida')->map(fn ($folio) => [
                    'id' => $folio->id,
                    'folio' => $folio->folio,
                    'banda' => $folio->banda,
                    'posicion' => $folio->posicion,
                    'altura' => $folio->altura,
                    'nivel' => $folio->nivel,
                    'especie' => $folio->especie,
                    'variedad' => $folio->variedad,
                    'productor' => $folio->productor,
                    'embalaje' => $folio->embalaje,
                    'cajas' => $folio->cajas,
                    'pallets' => $folio->pallets,
                    'temperatura_inicial' => $folio->temperatura_inicial,
                    'temperatura_inicio' => $folio->temperatura_inicio,
                    'temperatura_inversion_interior' => $folio->temperatura_inversion_interior,
                    'temperatura_inversion_exterior' => $folio->temperatura_inversion_exterior,
                    'temperatura_final_interna' => $folio->temperatura_final_interna,
                    'temperatura_final_externa' => $folio->temperatura_final_externa,
                    'atributos' => $folio->atributos,
                ])->values();
            }
        }

        $camaras = PreCoolingCamara::with('parametros')
            ->where('activo', true)
            ->orderBy('codigo')
            ->get()
            ->map(fn (PreCoolingCamara $camara) => [
                'id' => $camara->id,
                'codigo' => $camara->codigo,
                'nombre' => $camara->nombre,
                'parametros' => $camara->parametrosPorDimension(),
            ])
            ->values();

        $tiposProcesos = PreCoolingTipoProceso::where('activo', true)->orderBy('codigo')->get();
        $atributos = PreCoolingAtributo::where('activo', true)->orderBy('codigo')->get();

        return Inertia::render('PreCooling/MatrizTunel', [
            'tuneles' => $tuneles,
            'tunel' => $tunel,
            'parametros' => $parametros,
            'cargaActiva' => $cargaActiva,
            'folios' => $folios,
            'camaras' => $camaras,
            'tiposProcesos' => $tiposProcesos,
            'atributos' => $atributos,
        ]);
    }

    public function camara(Request $request)
    {
        $this->authorizePreCooling($request);

        $camaras = PreCoolingCamara::orderBy('codigo')->get();

        $camaraId = (int) $request->query('camara_id', 0);
        $camara = $camaraId ? $camaras->firstWhere('id', $camaraId) : null;

        $parametros = [];
        $saldos = [];

        if ($camara) {
            $camara->load('parametros');
            $parametros = $camara->parametrosPorDimension();

            $saldos = PreCoolingSaldo::with(['tipoProceso', 'loadFolio.carga.tunel'])
                ->where('camara_id', $camara->id)
                ->orderBy('banda')
                ->orderBy('fila')
                ->orderBy('columna')
                ->orderBy('altura')
                ->orderBy('nivel')
                ->get()
                ->map(fn (PreCoolingSaldo $saldo) => [
                    'id' => $saldo->id,
                    'banda' => $saldo->banda,
                    'fila' => $saldo->fila,
                    'columna' => $saldo->columna,
                    'altura' => $saldo->altura,
                    'nivel' => $saldo->nivel,
                    'folio' => $saldo->folio,
                    'tipo_proceso' => $saldo->tipoProceso?->codigo,
                    'tipo_proceso_nombre' => $saldo->tipoProceso?->nombre,
                    'cajas' => $saldo->cajas,
                    'pallets' => $saldo->pallets,
                    'especie' => $saldo->especie,
                    'variedad' => $saldo->variedad,
                    'productor' => $saldo->productor,
                    'exportadora' => $saldo->loadFolio?->exportadora,
                    'embalaje' => $saldo->loadFolio?->embalaje,
                    'categoria' => $saldo->loadFolio?->categoria,
                    'calibre' => $saldo->loadFolio?->calibre,
                    'proceso_numero' => $saldo->loadFolio?->carga?->numero,
                    'tunel' => $saldo->loadFolio?->carga?->tunel?->codigo,
                    'fecha_hora_salida' => $saldo->loadFolio?->fecha_hora_salida?->format('Y-m-d H:i'),
                    'temperaturas' => $saldo->loadFolio?->temperature_by_type,
                    'temperatura_ambiente_tunel_salida' => $saldo->loadFolio?->temperatura_ambiente_tunel_salida,
                    'temperatura_ambiente_camara_salida' => $saldo->loadFolio?->temperatura_ambiente_camara_salida,
                ])->values();
        }

        return Inertia::render('PreCooling/MatrizCamara', [
            'camaras' => $camaras,
            'camara' => $camara,
            'parametros' => $parametros,
            'saldos' => $saldos,
            'tiposProcesos' => PreCoolingTipoProceso::query()
                ->where('activo', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
        ]);
    }
}
