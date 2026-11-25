<?php

namespace App\Services\Planning;

use App\Models\PackingLine;
use App\Models\PackingProcess;
use App\Models\PackingProcessLot;
use App\Models\Shift;
use Illuminate\Support\Collection;

class MobilePlanningService
{
    public function __construct(
        private readonly InventoryRepositorySqlsrv $inventory,
        private readonly ProcessProgressSqlsrvService $progress,
    ) {
    }

    public function build(array $filters = []): array
    {
        $fecha = trim((string) ($filters['fecha'] ?? now()->toDateString()));
        $variedades = $this->values($filters['variedad'] ?? null);
        $lineas = $this->values($filters['linea'] ?? null);
        $turnos = $this->values($filters['turno'] ?? null);
        $exportadoras = $this->values($filters['exportadora'] ?? null);
        $estado = $this->estado($filters['estado'] ?? null);

        $avances = $this->progress->currentByProcess();
        $numerosLocales = PackingProcessLot::query()
            ->whereHas('process', fn ($query) => $query->whereDate('fecha', $fecha))
            ->whereNotNull('numproceso')
            ->pluck('numproceso')
            ->map(fn ($value) => ProcessProgressSqlsrvService::normalizeProcessNumber($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $enCurso = collect($avances)
            ->reject(fn (array $avance, $numero) => in_array((string) $numero, $numerosLocales, true))
            ->map(fn (array $avance): array => [
                'numero_proceso' => (string) ($avance['numero_proceso'] ?? ''),
                'inicio' => (string) ($avance['inicio'] ?? ''),
                'porcentaje_avance' => (float) ($avance['porcentaje_avance'] ?? 0),
                'kilos_planificados' => (float) ($avance['kilos_planificados'] ?? 0),
                'kilos_vaciados' => (float) ($avance['kilos_vaciados'] ?? 0),
            ])
            ->values()
            ->all();
        $planificados = $this->plannedRows($fecha, $variedades, $lineas, $turnos, $exportadoras);
        $plannedNgs = $planificados
            ->pluck('n_g_recepcion')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $stockNgs = $this->inventory->getStockNumbers($plannedNgs)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        $planificados = $planificados->map(function (array $fila) use ($stockNgs, $avances): array {
            $ng = trim((string) ($fila['n_g_recepcion'] ?? ''));
            $fila['estado'] = in_array($ng, $stockNgs, true) ? 'Planificado' : 'Procesado';

            $avance = $avances[ProcessProgressSqlsrvService::normalizeProcessNumber($fila['numproceso'] ?? '')] ?? null;
            $fila['procesando'] = $avance !== null;
            $fila['porcentaje_avance'] = $avance['porcentaje_avance'] ?? null;
            $fila['avance'] = $avance;

            return $fila;
        });
        $disponibles = $this->availableRows($fecha, $variedades, $exportadoras, $plannedNgs)
            ->map(function (array $fila): array {
                $fila['procesando'] = false;
                $fila['porcentaje_avance'] = null;
                $fila['avance'] = null;

                return $fila;
            });
        $planificados = $this->applyEstado($planificados, $estado, true);
        $disponibles = $this->applyEstado($disponibles, $estado, false);
        $filas = $planificados->concat($disponibles)->values();

        return [
            'detalle' => $filas->all(),
            'en_curso' => $enCurso,
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
            ->with(['lots' => fn ($query) => $query->with('packingLine')->orderBy('orden')->orderBy('id'), 'shift'])
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
                        $numproceso = trim((string) ($lot->numproceso ?? ''));
                        if ($numproceso === '') {
                            $numproceso = '#'.$lot->id;
                        }

                        return [
                            'clave' => 'plan-'.$process->id.'-'.$lot->id,
                            'orden' => (int) ($lot->orden ?? 0),
                            'n_g_recepcion' => $ng,
                            'numproceso' => $numproceso,
                            'fecha_proceso' => $process->fecha?->toDateString(),
                            'cantidad_bins' => (int) ($lot->cantidad_bins ?? 0),
                            'peso_neto' => round((float) ($lot->peso_neto ?? 0), 3),
                            'porcentaje_exportacion' => $lot->porcentaje_exportacion === null
                                ? null
                                : round((float) $lot->porcentaje_exportacion, 2),
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
                    'porcentaje_exportacion' => null,
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
            ->groupBy('turno')
            ->map(function (Collection $turnoRows, string $turno) {
                $lineas = $turnoRows
                    ->groupBy('linea')
                    ->map(function (Collection $lineaRows, string $linea) {
                        $procesos = $lineaRows
                            ->groupBy(function (array $fila) {
                                return $fila['planned'] ? $fila['numproceso'] : 'Sin proceso';
                            })
                            ->map(function (Collection $procesoRows, string $proceso) {
                                $avance = $procesoRows->firstWhere('planned', true)['avance'] ?? null;

                                return [
                                    'proceso' => $proceso,
                                    'procesando' => $avance !== null,
                                    'avance' => $avance,
                                    'porcentaje_avance' => $avance['porcentaje_avance'] ?? null,
                                    'kilos_planificados' => $avance['kilos_planificados'] ?? 0.0,
                                    'kilos_vaciados' => $avance['kilos_vaciados'] ?? 0.0,
                                    'lotes' => $procesoRows->count(),
                                    'kilos' => round((float) $procesoRows->sum('peso_neto'), 3),
                                    'bins' => (int) $procesoRows->sum('cantidad_bins'),
                                    'planificados' => $procesoRows->where('planned', true)->count(),
                                    'disponibles' => $procesoRows->where('planned', false)->count(),
                                    'filas' => $procesoRows
                                        ->sort(fn (array $a, array $b) => $this->compareFilas($a, $b))
                                        ->values()
                                        ->all(),
                                ];
                            })
                            ->sort(fn (array $a, array $b) => $this->comparePriority($a, $b)
                                ?: $this->compareGroupLabels($a['proceso'], $b['proceso'], 'Sin proceso'))
                            ->values()
                            ->all();

                        return [
                            'linea' => $linea,
                            'procesando' => collect($procesos)->contains('procesando', true),
                            'lotes' => $lineaRows->count(),
                            'kilos' => round((float) $lineaRows->sum('peso_neto'), 3),
                            'bins' => (int) $lineaRows->sum('cantidad_bins'),
                            'planificados' => $lineaRows->where('planned', true)->count(),
                            'disponibles' => $lineaRows->where('planned', false)->count(),
                            'procesos' => $procesos,
                        ];
                    })
                    ->sort(fn (array $a, array $b) => $this->comparePriority($a, $b)
                        ?: $this->compareGroupLabels($a['linea'], $b['linea'], 'Sin línea'))
                    ->values()
                    ->all();

                return [
                    'turno' => $turno,
                    'procesando' => collect($lineas)->contains('procesando', true),
                    'lotes' => $turnoRows->count(),
                    'kilos' => round((float) $turnoRows->sum('peso_neto'), 3),
                    'bins' => (int) $turnoRows->sum('cantidad_bins'),
                    'planificados' => $turnoRows->where('planned', true)->count(),
                    'disponibles' => $turnoRows->where('planned', false)->count(),
                    'lineas' => $lineas,
                ];
            })
            ->sort(fn (array $a, array $b) => $this->comparePriority($a, $b)
                ?: $this->compareGroupLabels($a['turno'], $b['turno'], 'Sin turno'))
            ->values()
            ->all();
    }

    private function comparePriority(array $a, array $b): int
    {
        $aPriority = ! empty($a['procesando']) ? 0 : 1;
        $bPriority = ! empty($b['procesando']) ? 0 : 1;

        return $aPriority <=> $bPriority;
    }

    private function compareFilas(array $a, array $b): int
    {
        if (! empty($a['planned']) && ! empty($b['planned'])) {
            return ((int) ($a['orden'] ?? 0)) <=> ((int) ($b['orden'] ?? 0))
                ?: $this->compareGroupLabels($a['n_g_recepcion'], $b['n_g_recepcion'], '');
        }

        return $this->compareGroupLabels($a['n_g_recepcion'], $b['n_g_recepcion'], '');
    }

    private function compareGroupLabels(mixed $a, mixed $b, string $empty): int
    {
        $aText = trim((string) $a);
        $bText = trim((string) $b);
        $aEmpty = $aText === '' || $aText === '—' || $aText === $empty;
        $bEmpty = $bText === '' || $bText === '—' || $bText === $empty;

        if ($aEmpty !== $bEmpty) {
            return $aEmpty ? 1 : -1;
        }

        return strnatcasecmp($aText, $bText);
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

    private function estado(mixed $estado): string
    {
        $estado = trim((string) $estado);

        return in_array($estado, ['todo', 'planificado', 'procesado', 'sin_planificar'], true) ? $estado : 'todo';
    }

    private function applyEstado(Collection $filas, string $estado, bool $planned): Collection
    {
        if ($estado === 'planificado') {
            return $planned ? $filas->where('estado', 'Planificado') : collect();
        }

        if ($estado === 'procesado') {
            return $planned ? $filas->where('estado', 'Procesado') : collect();
        }

        if ($estado === 'sin_planificar') {
            return $planned ? collect() : $filas;
        }

        return $filas;
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
