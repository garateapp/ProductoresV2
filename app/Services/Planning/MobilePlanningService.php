<?php

namespace App\Services\Planning;

use App\Models\PackingLine;
use App\Models\PackingProcess;
use App\Models\PackingProcessLot;
use App\Models\Shift;
use Illuminate\Support\Collection;

class MobilePlanningService
{
    public function __construct(private readonly InventoryRepositorySqlsrv $inventory)
    {
    }

    public function build(array $filters = []): array
    {
        $fecha = trim((string) ($filters['fecha'] ?? now()->toDateString()));
        $variedades = $this->values($filters['variedad'] ?? null);
        $lineas = $this->values($filters['linea'] ?? null);
        $turnos = $this->values($filters['turno'] ?? null);
        $exportadoras = $this->values($filters['exportadora'] ?? null);

        $planificados = $this->plannedRows($fecha, $variedades, $lineas, $turnos, $exportadoras);
        $plannedNgs = $planificados
            ->pluck('n_g_recepcion')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $disponibles = $this->availableRows($fecha, $variedades, $exportadoras, $plannedNgs);
        $filas = $planificados->concat($disponibles)->values();

        return [
            'detalle' => $filas->all(),
            'grupos' => $this->groupRows($filas),
            'resumen' => $this->resumen($planificados, $disponibles),
            'options' => [
                'lineas' => $this->lineOptions(),
                'turnos' => $this->shiftOptions(),
            ],
        ];
    }

    private function plannedRows(
        string $fecha,
        array $variedades,
        array $lineas,
        array $turnos,
        array $exportadoras,
    ): Collection {
        $query = PackingProcess::query()
            ->with(['lots.packingLine', 'shift'])
            ->whereDate('fecha', $fecha);

        if ($exportadoras !== []) {
            $query->whereIn('exportadora', $exportadoras);
        }
        if ($turnos !== []) {
            $query->whereIn('shift_id', $turnos);
        }
        if ($variedades !== [] || $lineas !== []) {
            $query->whereHas('lots', function ($query) use ($variedades, $lineas) {
                if ($variedades !== []) {
                    $query->where(function ($query) use ($variedades) {
                        $query->whereIn('n_variedad', $variedades)
                            ->orWhereIn('variedad_original', $variedades);
                    });
                }
                if ($lineas !== []) {
                    $query->whereIn('packing_line_id', $lineas);
                }
            });
        }

        return $query
            ->get()
            ->flatMap(function (PackingProcess $process) use ($variedades, $lineas) {
                return $process->lots
                    ->filter(function (PackingProcessLot $lot) use ($variedades, $lineas) {
                        $variedad = trim((string) ($lot->n_variedad ?? $lot->variedad_original ?? ''));
                        if ($variedades !== [] && ! in_array($variedad, $variedades, true)) {
                            return false;
                        }
                        if ($lineas !== [] && ! in_array((string) $lot->packing_line_id, $lineas, true)) {
                            return false;
                        }
                        return true;
                    })
                    ->map(function (PackingProcessLot $lot) use ($process) {
                        $ng = trim((string) ($lot->n_g_recepcion ?? ''));
                        if ($ng === '') {
                            return null;
                        }

                        $variedad = trim((string) ($lot->n_variedad ?? $lot->variedad_original ?? ''));
                        $linea = trim((string) ($lot->packingLine?->nombre ?? '')) ?: 'Sin línea';
                        $turno = trim((string) ($process->shift?->nombre ?? $process->shift?->codigo ?? '')) ?: 'Sin turno';
                        $fechaRecepcion = $lot->fecha_recepcion;
                        if ($fechaRecepcion !== null && method_exists($fechaRecepcion, 'toDateString')) {
                            $fechaRecepcion = $fechaRecepcion->toDateString();
                        }
                        $numproceso = trim((string) ($process->numproceso ?? ''));
                        if ($numproceso === '') {
                            $numproceso = '#'.$process->id;
                        }

                        return [
                            'clave' => 'plan-'.$process->id.'-'.$lot->id,
                            'n_g_recepcion' => $ng,
                            'numproceso' => $numproceso,
                            'fecha_proceso' => $process->fecha?->toDateString(),
                            'cantidad_bins' => (int) ($lot->cantidad_bins ?? 0),
                            'peso_neto' => round((float) ($lot->peso_neto ?? 0), 3),
                            'n_variedad' => $variedad !== '' ? $variedad : '—',
                            'n_exportadora' => trim((string) ($process->exportadora ?? '')) ?: '(sin exportadora)',
                            'n_productor' => trim((string) ($lot->n_productor ?? $lot->csg_productor ?? '')) ?: '—',
                            'linea' => $linea,
                            'turno' => $turno,
                            'planned' => true,
                            'estado' => 'Planificado',
                            'detalle' => [
                                'productor' => trim((string) ($lot->n_productor ?? $lot->csg_productor ?? '')) ?: '—',
                                'exportadora' => trim((string) ($process->exportadora ?? '')) ?: '(sin exportadora)',
                                'variedad' => $variedad !== '' ? $variedad : '—',
                                'fecha_proceso' => $process->fecha?->toDateString() ?? '—',
                                'fecha_recepcion' => $fechaRecepcion ?: '—',
                                'categoria' => trim((string) ($lot->categoria_origen ?? '')) ?: '—',
                                'nota_calidad' => trim((string) ($lot->setup_nota_calidad ?? '')) ?: '—',
                                'centro_costo' => trim((string) ($lot->sdp_centrocosto ?? '')) ?: '—',
                                'referencia' => trim((string) ($lot->sispro ?? '')) ?: '—',
                                'linea' => $linea,
                                'turno' => $turno,
                            ],
                        ];
                    })
                    ->filter();
            })
            ->values();
    }

    private function availableRows(
        string $fecha,
        array $variedades,
        array $exportadoras,
        array $plannedNgs,
    ): Collection {
        return $this->inventory
            ->getAvailableLots([
                'id_empresa' => 1,
                'creacion_tipo' => 'RFG',
                'variedad' => $variedades,
                'exportadora' => $exportadoras,
                'fecha_from' => $fecha,
                'fecha_to' => $fecha,
                'exclude_n_g_recepcion' => $plannedNgs,
                'limit' => 5000,
            ])
            ->filter(function (array $row) use ($variedades, $exportadoras, $plannedNgs) {
                $ng = trim((string) ($row['n_g_recepcion'] ?? ''));
                if ($ng === '' || in_array($ng, $plannedNgs, true)) {
                    return false;
                }

                $variedad = trim((string) ($row['n_variedad'] ?? $row['variedad'] ?? ''));
                if ($variedades !== [] && ! in_array($variedad, $variedades, true)) {
                    return false;
                }

                $exportadora = trim((string) ($row['n_exportadora'] ?? $row['c_exportadora'] ?? ''));
                if ($exportadoras !== [] && ! in_array($exportadora, $exportadoras, true)) {
                    return false;
                }

                return true;
            })
            ->map(function (array $row) {
                $ng = trim((string) ($row['n_g_recepcion'] ?? ''));
                $variedad = trim((string) ($row['n_variedad'] ?? $row['variedad'] ?? ''));
                $exportadora = trim((string) ($row['n_exportadora'] ?? $row['c_exportadora'] ?? ''));
                $fechaRecepcion = $row['fecha_recepcion'] ?? null;
                if ($fechaRecepcion !== null && method_exists($fechaRecepcion, 'toDateString')) {
                    $fechaRecepcion = $fechaRecepcion->toDateString();
                }

                return [
                    'clave' => 'stock-'.$ng,
                    'n_g_recepcion' => $ng,
                    'numproceso' => '—',
                    'fecha_proceso' => null,
                    'cantidad_bins' => (int) round((float) ($row['cantidad_bins'] ?? 0)),
                    'peso_neto' => round((float) ($row['peso_neto'] ?? 0), 3),
                    'n_variedad' => $variedad !== '' ? $variedad : '—',
                    'n_exportadora' => $exportadora !== '' ? $exportadora : '(sin exportadora)',
                    'n_productor' => trim((string) ($row['n_productor'] ?? $row['csg_productor'] ?? '')) ?: '—',
                    'linea' => 'Sin línea',
                    'turno' => 'Sin turno',
                    'planned' => false,
                    'estado' => 'Disponible',
                    'detalle' => [
                        'productor' => trim((string) ($row['n_productor'] ?? $row['csg_productor'] ?? '')) ?: '—',
                        'exportadora' => $exportadora !== '' ? $exportadora : '(sin exportadora)',
                        'variedad' => $variedad !== '' ? $variedad : '—',
                        'fecha_proceso' => '—',
                        'fecha_recepcion' => $fechaRecepcion ?: '—',
                        'categoria' => trim((string) ($row['n_categoria'] ?? '')) ?: '—',
                        'nota_calidad' => trim((string) ($row['nota_calidad_sqlsrv'] ?? '')) ?: '—',
                        'centro_costo' => trim((string) ($row['c_centrocosto'] ?? '')) ?: '—',
                        'referencia' => trim((string) ($row['referencias_recepcion'] ?? '')) ?: '—',
                        'linea' => 'Sin línea',
                        'turno' => 'Sin turno',
                    ],
                ];
            })
            ->values();
    }

    private function groupRows(Collection $filas): array
    {
        return $filas
            ->groupBy('linea')
            ->map(function (Collection $lineRows, string $linea) {
                $turnos = $lineRows
                    ->groupBy('turno')
                    ->map(function (Collection $turnRows, string $turno) {
                        return [
                            'turno' => $turno,
                            'lotes' => $turnRows->count(),
                            'kilos' => round((float) $turnRows->sum('peso_neto'), 3),
                            'bins' => (int) $turnRows->sum('cantidad_bins'),
                            'planificados' => $turnRows->where('planned', true)->count(),
                            'disponibles' => $turnRows->where('planned', false)->count(),
                            'filas' => $turnRows->values()->all(),
                        ];
                    })
                    ->sort(function (array $a, array $b) {
                        $aSin = $a['turno'] === 'Sin turno';
                        $bSin = $b['turno'] === 'Sin turno';
                        if ($aSin !== $bSin) {
                            return $aSin ? 1 : -1;
                        }
                        return strcasecmp((string) $a['turno'], (string) $b['turno']);
                    })
                    ->values()
                    ->all();

                return [
                    'linea' => $linea,
                    'lotes' => $lineRows->count(),
                    'kilos' => round((float) $lineRows->sum('peso_neto'), 3),
                    'bins' => (int) $lineRows->sum('cantidad_bins'),
                    'turnos' => $turnos,
                ];
            })
            ->sort(function (array $a, array $b) {
                $aSin = $a['linea'] === 'Sin línea';
                $bSin = $b['linea'] === 'Sin línea';
                if ($aSin !== $bSin) {
                    return $aSin ? 1 : -1;
                }
                return strcasecmp((string) $a['linea'], (string) $b['linea']);
            })
            ->values()
            ->all();
    }

    private function resumen(Collection $planificados, Collection $disponibles): array
    {
        return [
            'kilos' => round((float) $planificados->sum('peso_neto') + (float) $disponibles->sum('peso_neto'), 3),
            'bins' => (int) ($planificados->sum('cantidad_bins') + $disponibles->sum('cantidad_bins')),
            'lotes' => $planificados->count() + $disponibles->count(),
            'planificados' => [
                'lotes' => $planificados->count(),
                'kilos' => round((float) $planificados->sum('peso_neto'), 3),
                'bins' => (int) $planificados->sum('cantidad_bins'),
            ],
            'disponibles' => [
                'lotes' => $disponibles->count(),
                'kilos' => round((float) $disponibles->sum('peso_neto'), 3),
                'bins' => (int) $disponibles->sum('cantidad_bins'),
            ],
        ];
    }

    private function lineOptions(): array
    {
        return PackingLine::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn ($linea) => ['value' => (string) $linea->id, 'label' => (string) $linea->nombre])
            ->all();
    }

    private function shiftOptions(): array
    {
        return Shift::query()
            ->where('activo', true)
            ->orderBy('hora_inicio')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn ($turno) => [
                'value' => (string) $turno->id,
                'label' => trim((string) ($turno->nombre ?? $turno->codigo ?? '')) ?: (string) $turno->codigo,
            ])
            ->all();
    }

    private function values(mixed $values): array
    {
        if ($values === null || $values === '') {
            return [];
        }

        if (! is_array($values)) {
            $values = [$values];
        }

        return collect($values)
            ->flatMap(fn ($value) => preg_split('/\s*,\s*/', (string) $value) ?: [])
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
