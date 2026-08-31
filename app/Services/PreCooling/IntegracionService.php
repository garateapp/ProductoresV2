<?php

namespace App\Services\PreCooling;

use App\Models\PreCoolingIntegrationRead;
use App\Models\PreCoolingLoad;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IntegracionService
{
    public function generar(?string $fechaInicio = null, ?string $fechaFin = null, ?string $estado = null): array
    {
        $query = PreCoolingLoad::with(['tipoProceso', 'tunel', 'camaraDestino', 'folios']);

        if ($fechaInicio) {
            $query->where('fecha_hora_inicio', '>=', Carbon::parse($fechaInicio)->toDateTimeString());
        }
        if ($fechaFin) {
            $query->where('fecha_hora_inicio', '<=', Carbon::parse($fechaFin)->toDateTimeString());
        }
        if ($estado) {
            $query->where('estado', $estado);
        }

        $loads = $query->orderByDesc('id')->get();

        $data = [];
        $anyPartial = false;

        foreach ($loads as $load) {
            $payload = $this->buildLoad($load);

            if ($payload['isPartialSuccess']) {
                $anyPartial = true;
            }

            $data[] = $payload['data'];
        }

        return [
            'isSucess' => true,
            'isPartialSuccess' => $anyPartial ? true : null,
            'data' => $data,
        ];
    }

    protected function buildLoad(PreCoolingLoad $load): array
    {
        $terminado = $load->estado === 'salido' && $load->fecha_hora_fin !== null;
        $isPartialSuccess = $terminado ? null : true;

        $detalles = [];
        $found = [];
        $missing = [];

        foreach ($load->folios as $folio) {
            $sqlsrv = $this->buscarEnVista((string) $folio->folio);

            if ($sqlsrv === null) {
                $missing[] = (string) $folio->folio;
                continue;
            }

            $found[] = (string) $folio->folio;

            $detalles[] = $this->buildFolio($load, $folio, $sqlsrv);
        }

        $this->registrarLectura($load, $found, $missing, $isPartialSuccess);

        $payload = [
            'codeSeason' => '2627',
            'codeSubsidiary' => '1',
            'nameProcessType' => $load->tipoProceso?->nombre,
            'namePreColdStatus' => $this->estadoNombre($load->estado),
            'preColdNumber' => $load->numero,
            'nameWarehousePreCold' => $load->tunel?->nombre,
            'nameWarehouseDestination' => $load->camaraDestino?->nombre,
            'startDate' => $this->fmtFecha($load->fecha_hora_inicio),
            'thermalInversionDate' => $this->fmtFecha($load->fecha_hora_inversion),
            'endDate' => $this->fmtFecha($load->fecha_hora_fin),
            'dischargeDate' => $this->fmtFecha($load->fecha_hora_termino),
            'durationTime' => $this->duracion($load->fecha_hora_inicio, $load->fecha_hora_fin),
            'temperatureSetPoint' => $load->temperatura_objetivo !== null
                ? (float) $load->temperatura_objetivo
                : null,
            'vigency' => true,
            'userCreated' => $load->usuario_inicio_id,
            'dateCreated' => $this->fmtFechaMs($load->created_at),
            'userModified' => $load->usuario_inversion_id,
            'dateModified' => $this->fmtFechaMs($load->updated_at),
            'lstPrecoldDetail' => $detalles,
        ];

        return [
            'data' => $payload,
            'isPartialSuccess' => $isPartialSuccess,
        ];
    }

    protected function buildFolio(PreCoolingLoad $load, $folio, array $sqlsrv): array
    {
        return [
            'preColdNumber' => $load->numero,
            'sinkNumber' => (string) $folio->folio,
            'codeSpecies' => $sqlsrv['codeSpecies'],
            'codeExtSpecies' => $sqlsrv['codeExtSpecies'],
            'nameSpecies' => $sqlsrv['nameSpecies'],
            'codeVariety' => $sqlsrv['codeVariety'],
            'nameVariety' => $sqlsrv['nameVariety'],
            'codeExtVariety' => $sqlsrv['codeExtVariety'],
            'codeContainer' => $sqlsrv['codeContainer'],
            'codeIntegrationContainer' => null,
            'codePackaging' => $sqlsrv['codePackaging'],
            'codeLabel' => $sqlsrv['codeLabel'],
            'band' => $this->band($folio->banda),
            'position' => $this->toInt($folio->posicion),
            'height' => $this->toInt($folio->altura),
            'level' => $this->toInt($folio->nivel),
            'quantityContainer' => $sqlsrv['quantityContainer'],
            'weight' => $sqlsrv['weight'],
            'isReprocessing' => (int) $load->tipo_proceso_id === 1,
            'vigency' => true,
            'temperatureByType' => $folio->temperature_by_type,
            'userCreated' => $load->usuario_inicio_id,
            'dateCreated' => $this->fmtFechaMs($folio->created_at),
            'userModified' => $load->usuario_inversion_id,
            'dateModified' => $this->fmtFechaMs($folio->updated_at),
        ];
    }

    protected function buscarEnVista(string $folio): ?array
    {
        $view = (string) config('services.termo.sqlsrv_view', 'V_PKG_Produccion_Salidas_XXX');

        try {
            $rows = DB::connection('sqlsrv')->select(
                <<<SQL
                SELECT n_exportadora as esportadora,
                       folio as folio,
                       n_productor as productor,
                       c_especie as codeSpecies,
                       id_especie as codeExtSpecies,
                       n_especie as nameSpecies,
                       id_variedad as codeExtVariety,
                       c_variedad as codeVariety,
                       n_variedad as nameVariety,
                       c_embalaje as codePackaging,
                       c_contenedor as codeContainer,
                       n_categoria as categoria,
                       n_calibre as calibre,
                       c_etiqueta as codeLabel,
                       SUM(cantidad) as quantityContainer,
                       SUM(peso_neto) as weight
                  FROM {$view}
                 WHERE folio = ?
                 GROUP BY n_exportadora, folio, n_productor, id_especie, c_especie, n_especie,
                          id_variedad, c_variedad, n_variedad, c_embalaje, n_categoria, n_calibre, c_etiqueta, c_contenedor
                SQL,
                [$folio]
            );
        } catch (\Throwable $e) {
            Log::error('PRECOOLING_INTEGRACION_VIEW_ERROR', [
                'folio' => $folio,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (empty($rows)) {
            return null;
        }

        $primero = $rows[0];
        $totalCantidad = 0.0;
        $totalPeso = 0.0;
        foreach ($rows as $r) {
            $totalCantidad += (float) $r->quantityContainer;
            $totalPeso += (float) $r->weight;
        }

        return [
            'codeSpecies' => $primero->codeSpecies ?? null,
            'codeExtSpecies' => $primero->codeExtSpecies ?? null,
            'nameSpecies' => $primero->nameSpecies ?? null,
            'codeVariety' => $primero->codeVariety ?? null,
            'nameVariety' => $primero->nameVariety ?? null,
            'codeExtVariety' => $primero->codeExtVariety ?? null,
            'codeContainer' => $primero->codeContainer ?? null,
            'codePackaging' => $primero->codePackaging ?? null,
            'codeLabel' => $primero->codeLabel ?? null,
            'quantityContainer' => (int) $totalCantidad,
            'weight' => round($totalPeso, 2),
        ];
    }

    protected function registrarLectura(PreCoolingLoad $load, array $found, array $missing, ?bool $isPartialSuccess): void
    {
        PreCoolingIntegrationRead::updateOrCreate(
            ['pre_cooling_load_id' => $load->id],
            [
                'folios_found' => $found,
                'folios_missing' => $missing,
                'is_partial_success' => $isPartialSuccess,
                'read_at' => now(),
            ]
        );
    }

    protected function band(?string $banda): ?int
    {
        if ($banda === null) {
            return null;
        }

        $b = mb_strtolower($banda);

        if (str_contains($b, 'derecha')) {
            return 1;
        }
        if (str_contains($b, 'izquierda')) {
            return 2;
        }

        return null;
    }

    protected function estadoNombre(string $estado): ?string
    {
        return match ($estado) {
            'ingresado' => 'Ingresado',
            'iniciado' => 'Iniciado',
            'salido' => 'Terminado',
            default => $estado,
        };
    }

    protected function duracion($inicio, $fin): ?string
    {
        if (! $inicio || ! $fin) {
            return null;
        }

        $d1 = Carbon::parse($inicio);
        $d2 = Carbon::parse($fin);
        $seg = max(0, (int) $d1->diffInSeconds($d2, false));

        $h = intdiv($seg, 3600);
        $m = intdiv($seg % 3600, 60);
        $s = $seg % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    protected function toInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function fmtFecha($value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d\TH:i:s') : null;
    }

    protected function fmtFechaMs($value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d\TH:i:s.v') : null;
    }
}
