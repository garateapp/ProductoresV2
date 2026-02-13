<?php

namespace App\Services\Planning;

use App\Enums\PlanningLotStatus;
use App\Models\PackingLine;
use App\Models\PackingProcess;
use App\Models\PackingProcessLot;
use App\Models\Variedad;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProcessGeneratorService
{
    public function __construct(
        private readonly InventoryRepositorySqlsrv $inventoryRepository,
        private readonly QualityRepositoryMysql $qualityRepository,
        private readonly CapacityResolverService $capacityResolver,
        private readonly CarozosPackagingMatrixService $carozosPackagingMatrix,
    ) {
    }

    /**
     * Genera propuesta de planificación (lotes) para un proceso.
     *
     * Heurística:
     * - FIFO por antigüedad/fecha_recepcion
     * - Dentro de los N más antiguos (config fifo_window), elegir menor costo de cambio de SETUP
     * - Por defecto NO divide lote (config allow_split)
     */
    public function generate(PackingProcess $process, array $options = []): void
    {
        DB::transaction(function () use ($process, $options) {
            $process->loadMissing(['shift', 'lineOverrides']);
            $isRepack = Str::lower(trim((string) ($process->planning_mode ?? 'normal'))) === 'reembalaje';
            $hasSourceType = Schema::hasColumn('process_lots', 'source_type');
            $hasSourceKey = Schema::hasColumn('process_lots', 'source_key');
            $hasSourceFolio = Schema::hasColumn('process_lots', 'source_folio');
            $hasSourceNgProceso = Schema::hasColumn('process_lots', 'source_n_g_proceso');
            $hasSourceLote = Schema::hasColumn('process_lots', 'source_lote');
            $hasSourceCEmb = Schema::hasColumn('process_lots', 'source_c_embalaje');
            $hasSourceNEmb = Schema::hasColumn('process_lots', 'source_n_embalaje');
            $hasSourceCategoria = Schema::hasColumn('process_lots', 'source_categoria');
            $hasSourceSnapshot = Schema::hasColumn('process_lots', 'source_snapshot');

            $extraByLine = $process->lineOverrides
                ->mapWithKeys(fn ($r) => [(int) $r->packing_line_id => (float) $r->extra_horas])
                ->all();

            $includedLineIds = collect($process->included_packing_line_ids ?: [])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();

            $linesQuery = PackingLine::query()
                ->where('activo', true)
                ->forEspecie($process->especie);
            if ($includedLineIds->isNotEmpty()) {
                $linesQuery->whereIn('id', $includedLineIds->all());
            }
            $lines = $linesQuery->orderBy('nombre')->get();

            // Limpia propuesta anterior (solo mientras no esté confirmado)
            $process->lots()->delete();

            $maxKilos = array_key_exists('max_kilos', $options) ? (float) $options['max_kilos'] : null;
            $remainingKilos = $maxKilos;
            if ($maxKilos !== null && $maxKilos <= 0) {
                // No hay estimación para este día/especie → queda sin lotes.
                $this->estimateTimes($process);
                return;
            }

            if ($isRepack) {
                $inventory = $this->inventoryRepository->getRepackAvailableLots([
                    'especie' => $process->especie,
                    'variedad' => $options['variedad'] ?? null,
                    'limit' => 500,
                ])->values();
            } else {
                $inventory = $this->inventoryRepository->getAvailableLots([
                    'especie' => $process->especie,
                    'limit' => 500,
                    'exclude_n_g_recepcion' => $options['exclude_n_g_recepcion'] ?? [],
                ])->values();
            }

            $qualityMap = $isRepack
                ? []
                : $this->qualityRepository->getQualityByNGRecepcion($inventory->pluck('n_g_recepcion')->all());

            $candidates = $inventory->map(function (array $row) use ($qualityMap, $isRepack) {
                if ($isRepack) {
                    $setupHash = sha1(implode('|', [
                        (string) ($row['source_key'] ?? ''),
                        (string) ($row['variedad'] ?? ''),
                        (string) ($row['c_embalaje'] ?? ''),
                        (string) ($row['n_embalaje'] ?? ''),
                    ]));

                    return [
                        ...$row,
                        'calibre' => null,
                        'setup_nota_calidad' => $row['nota_calidad_sqlsrv'] ?? null,
                        'setup_calibre' => null,
                        'setup_color' => null,
                        'brix' => null,
                        'quality_warning' => false,
                        'setup_hash' => $setupHash,
                        'antiguedad' => null,
                    ];
                }

                $n = (string) $row['n_g_recepcion'];
                $q = $qualityMap[$n] ?? [
                    'setup_nota_calidad' => null,
                    'setup_calibre' => null,
                    'setup_color' => null,
                    'brix' => null,
                    'warning' => true,
                ];

                $setupHash = sha1(implode('|', [
                    (string) ($q['setup_nota_calidad'] ?? ''),
                    (string) ($q['setup_calibre'] ?? ($row['calibre'] ?? '')),
                    (string) ($row['variedad'] ?? ''),
                    (string) ($q['setup_color'] ?? ''),
                ]));

                return [
                    ...$row,
                    'setup_nota_calidad' => $q['setup_nota_calidad'],
                    // Si la vista ya trae calibre, lo mantenemos como fallback.
                    'setup_calibre' => $q['setup_calibre'] ?? ($row['calibre'] ?? null),
                    'setup_color' => $q['setup_color'],
                    'brix' => $q['brix'],
                    'quality_warning' => (bool) ($q['warning'] ?? false),
                    'setup_hash' => $setupHash,
                ];
            });

            if (! empty($options['allowed_variedades']) && is_array($options['allowed_variedades'])) {
                $allowed = collect($options['allowed_variedades'])
                    ->map(fn ($v) => $this->normalizeKeyPart((string) $v, false))
                    ->filter()
                    ->unique()
                    ->values();

                if ($allowed->isNotEmpty()) {
                    $allowedSet = array_fill_keys($allowed->all(), true);
                    $candidates = $candidates->filter(function (array $row) use ($allowedSet) {
                        $varKey = $this->normalizeKeyPart((string) ($row['variedad'] ?? ''), false);
                        return $varKey !== '' && isset($allowedSet[$varKey]);
                    })->values();
                }
            }

            $candidates = $candidates->sortBy([
                ['antiguedad', 'desc'],
                ['fecha_recepcion', 'asc'],
                ['fecha_produccion', 'asc'],
                ['source_key', 'asc'],
            ])->values();

            $variedadMap = Variedad::query()
                ->whereIn('name', $candidates->pluck('variedad')->filter()->unique()->values()->all())
                ->pluck('id', 'name');

            $fifoWindow = max(1, (int) config('planning.fifo_window', 10));
            $allowSplit = (bool) config('planning.allow_split', false);
            $maxScan = max($fifoWindow, (int) config('planning.max_scan', 50));

            $lotsToInsert = [];

            foreach ($lines as $line) {
                $binsPorHora = $this->capacityResolver->resolveBinsPorHora(
                    $line->id,
                    $process->especie,
                    $process->shift_id,
                    Carbon::parse($process->fecha)
                );

                if (! $binsPorHora || $binsPorHora <= 0) {
                    continue;
                }

                $extraHoras = (float) ($extraByLine[(int) $line->id] ?? 0);
                $remainingBins = (int) round($binsPorHora * ((float) $process->shift->horas + $extraHoras));
                if ($remainingBins <= 0) {
                    continue;
                }

                $prevSetup = null;
                $splitCounters = [];
                $lineOrder = 1;

                while ($remainingBins > 0 && $candidates->isNotEmpty()) {
                    if ($remainingKilos !== null && $remainingKilos <= 0) {
                        break;
                    }

                    $pick = $this->pickNextCandidate($candidates, $remainingBins, $prevSetup, $fifoWindow, $maxScan);
                    if (! $pick) {
                        break;
                    }

                    [$idx, $candidate] = $pick;
                    $candidateBins = (int) $candidate['cantidad_bins'];

                    $takeBins = $candidateBins;
                    $didSplit = false;

                    if ($candidateBins > $remainingBins) {
                        if (! $allowSplit) {
                            // No cabe y no dividimos → quitamos este candidato de la ventana para evitar loop.
                            $candidates->splice($idx, 1);
                            continue;
                        }

                        $takeBins = $remainingBins;
                        $didSplit = true;
                    }

                    $lotRecepcion = trim((string) ($candidate['n_g_recepcion'] ?? ''));
                    if ($lotRecepcion === '') {
                        $lotRecepcion = trim((string) ($candidate['loter_unitec'] ?? ''));
                    }
                    if ($lotRecepcion === '') {
                        $lotRecepcion = trim((string) ($candidate['source_key'] ?? ''));
                    }
                    $splitIndex = 1;
                    if ($isRepack) {
                        $splitCounters[$lotRecepcion] = ($splitCounters[$lotRecepcion] ?? 0) + 1;
                        $splitIndex = $splitCounters[$lotRecepcion];
                    } elseif ($didSplit) {
                        $splitCounters[$lotRecepcion] = ($splitCounters[$lotRecepcion] ?? 0) + 1;
                        $splitIndex = $splitCounters[$lotRecepcion];
                    }

                    $variedadId = null;
                    if (! empty($candidate['variedad'])) {
                        $variedadId = $variedadMap->get((string) $candidate['variedad']);
                    }

                    $pack = $this->carozosPackagingMatrix->suggest($candidate, [
                        'setup_nota_calidad' => $candidate['setup_nota_calidad'] ?? null,
                        'setup_calibre' => $candidate['setup_calibre'] ?? null,
                        'setup_color' => $candidate['setup_color'] ?? null,
                    ]);

                    $sourceType = trim((string) ($candidate['source_type'] ?? '')) ?: ($isRepack ? 'reembalaje' : 'recepcion');
                    $sourceKey = trim((string) ($candidate['source_key'] ?? '')) ?: trim((string) ($candidate['n_g_recepcion'] ?? ''));

                    $cEmbalaje = $pack['c_item'] ?? null;
                    $nEmbalaje = $pack['n_item'] ?? null;
                    if ($isRepack) {
                        $cEmbalaje = $cEmbalaje ?: ($candidate['c_embalaje'] ?? null);
                        $nEmbalaje = $nEmbalaje ?: ($candidate['n_embalaje'] ?? null);
                    }

                    $lotInsert = [
                        'process_id' => $process->id,
                        'packing_line_id' => $line->id,
                        'n_g_recepcion' => $lotRecepcion,
                        'split_index' => $splitIndex,
                        'setup_nota_calidad' => $candidate['setup_nota_calidad'],
                        'setup_calibre' => $candidate['setup_calibre'],
                        'setup_color' => $candidate['setup_color'],
                        'setup_hash' => $candidate['setup_hash'],
                        'brix' => $candidate['brix'],
                        'variedad_id' => $variedadId,
                        'n_variedad' => $candidate['variedad'] ?? null,
                        'id_productor' => isset($candidate['id_productor']) ? (int) $candidate['id_productor'] : null,
                        'c_productor' => $candidate['c_productor'] ?? null,
                        'csg_productor' => $candidate['csg_productor'] ?? null,
                        'n_productor' => $candidate['n_productor'] ?? ($candidate['productor'] ?? null),
                        // Snapshots para instructivo (formato xlsx)
                        'fecha_recepcion' => $candidate['fecha_recepcion'] ?? null,
                        'tipo_proceso' => $isRepack ? 'Reembalaje' : ($candidate['descripcion_tipo'] ?? null),
                        'variedad_original' => $candidate['n_variedad_original'] ?? null,
                        'productor_real' => $candidate['n_productor_original'] ?? ($candidate['n_productor'] ?? ($candidate['productor'] ?? null)),
                        'categoria_origen' => $candidate['categoria'] ?? ($candidate['n_categoria'] ?? null),
                        'sdp_centrocosto' => $candidate['sdp_centrocosto'] ?? null,
                        'envase_origen' => $candidate['n_embalaje'] ?? null,
                        'altura_origen' => $candidate['n_altura'] ?? null,
                        'destino' => isset($candidate['destino']) && trim((string) $candidate['destino']) !== ''
                            ? trim((string) $candidate['destino'])
                            : null,
                        // Embalaje sugerido por matriz (si hay match); siempre editable en UI.
                        'c_embalaje' => $cEmbalaje,
                        'n_embalaje' => $nEmbalaje,
                        'cp2_cajas_por_pallet' => $pack['cp2_cajas_por_pallet'] ?? null,
                        'cantidad_bins' => $takeBins,
                        // si se parte, prorrateamos peso proporcional
                        'peso_neto' => $this->proratePeso($candidate['peso_neto'], $takeBins, $candidateBins),
                        'orden' => $lineOrder++,
                        'inicio_estimado' => null,
                        'fin_estimado' => null,
                        'estado' => PlanningLotStatus::PROPUESTO->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($hasSourceType) {
                        $lotInsert['source_type'] = $sourceType;
                    }
                    if ($hasSourceKey) {
                        $lotInsert['source_key'] = $sourceKey !== '' ? $sourceKey : null;
                    }
                    if ($hasSourceFolio) {
                        $lotInsert['source_folio'] = isset($candidate['folio']) ? (string) $candidate['folio'] : null;
                    }
                    if ($hasSourceNgProceso) {
                        $lotInsert['source_n_g_proceso'] = isset($candidate['n_g_proceso']) ? (string) $candidate['n_g_proceso'] : null;
                    }
                    if ($hasSourceLote) {
                        $lotInsert['source_lote'] = isset($candidate['loter_unitec']) ? (string) $candidate['loter_unitec'] : null;
                    }
                    if ($hasSourceCEmb) {
                        $lotInsert['source_c_embalaje'] = isset($candidate['c_embalaje']) ? (string) $candidate['c_embalaje'] : null;
                    }
                    if ($hasSourceNEmb) {
                        $lotInsert['source_n_embalaje'] = isset($candidate['n_embalaje']) ? (string) $candidate['n_embalaje'] : null;
                    }
                    if ($hasSourceCategoria) {
                        $lotInsert['source_categoria'] = isset($candidate['categoria']) ? (string) $candidate['categoria'] : null;
                    }
                    if ($hasSourceSnapshot) {
                        $lotInsert['source_snapshot'] = $isRepack ? json_encode([
                            'folio' => $candidate['folio'] ?? null,
                            'n_g_proceso' => $candidate['n_g_proceso'] ?? null,
                            'n_g_recepcion' => $candidate['n_g_recepcion'] ?? null,
                            'loter_unitec' => $candidate['loter_unitec'] ?? null,
                            'destino' => $candidate['destino'] ?? null,
                            'c_embalaje' => $candidate['c_embalaje'] ?? null,
                            'n_embalaje' => $candidate['n_embalaje'] ?? null,
                            't_categoria' => $candidate['t_categoria'] ?? null,
                        ], JSON_UNESCAPED_UNICODE) : null;
                    }

                    $lotsToInsert[] = $lotInsert;

                    $remainingBins -= $takeBins;
                    if ($remainingKilos !== null) {
                        $lastPeso = (float) ($lotsToInsert[array_key_last($lotsToInsert)]['peso_neto'] ?? 0);
                        if ($lastPeso > 0) {
                            $remainingKilos -= $lastPeso;
                        }
                    }
                    $prevSetup = $this->setupFromCandidate($candidate);

                    if ($didSplit) {
                        // actualizamos el candidato con el remanente
                        $candidates[$idx]['cantidad_bins'] = $candidateBins - $takeBins;
                        $candidates[$idx]['peso_neto'] = $this->proratePeso($candidate['peso_neto'], $candidateBins - $takeBins, $candidateBins);
                    } else {
                        $candidates->splice($idx, 1);
                    }
                }
            }

            if (! empty($lotsToInsert)) {
                PackingProcessLot::insert($lotsToInsert);
            }

            $this->estimateTimes($process);
        });
    }

    public function estimateTimes(PackingProcess $process): void
    {
        // Ojo: este método se usa después de editar lotes; forzamos recarga para evitar datos stale.
        $process->load(['shift', 'lots', 'lineOverrides']);

        $extraByLine = $process->lineOverrides
            ->mapWithKeys(fn ($r) => [(int) $r->packing_line_id => (float) $r->extra_horas])
            ->all();

        $horaInicio = $process->shift->hora_inicio ?: '00:00:00';
        $baseStart = Carbon::parse($process->fecha->format('Y-m-d').' '.$horaInicio);

        // Calculamos por línea: secuencial según `orden`.
        $lotsByLine = $process->lots->groupBy('packing_line_id');
        foreach ($lotsByLine as $lineId => $lots) {
            $line = PackingLine::find($lineId);
            if (! $line) {
                continue;
            }

            $binsPorHora = $this->capacityResolver->resolveBinsPorHora(
                (int) $lineId,
                $process->especie,
                $process->shift_id,
                Carbon::parse($process->fecha)
            );
            if (! $binsPorHora || $binsPorHora <= 0) {
                continue;
            }

            $cursor = $baseStart->copy();
            foreach ($lots->sortBy('orden') as $lot) {
                $durationHours = max(0, (float) $lot->cantidad_bins / (float) $binsPorHora);
                $start = $cursor->copy();
                $end = $cursor->copy()->addMinutes((int) round($durationHours * 60));
                $cursor = $end->copy();

                $lot->forceFill([
                    'inicio_estimado' => $start,
                    'fin_estimado' => $end,
                ])->save();
            }
        }
    }

    private function pickNextCandidate(Collection $candidates, int $remainingBins, ?array $prevSetup, int $fifoWindow, int $maxScan): ?array
    {
        $window = $candidates->take($fifoWindow);
        $best = $this->pickBestFitFromWindow($window, $remainingBins, $prevSetup);
        if ($best) {
            return $best;
        }

        // Si nada "cabe" en la ventana FIFO, escaneamos un poco más para evitar quedarse sin plan.
        $scan = $candidates->take($maxScan);
        return $this->pickBestFitFromWindow($scan, $remainingBins, $prevSetup);
    }

    private function pickBestFitFromWindow(Collection $window, int $remainingBins, ?array $prevSetup): ?array
    {
        $bestIdx = null;
        $bestCandidate = null;
        $bestScore = null;

        foreach ($window as $idx => $candidate) {
            $bins = (int) ($candidate['cantidad_bins'] ?? 0);
            if ($bins <= 0) {
                continue;
            }

            // permitimos elegir aunque no quepa: la lógica de split decide después
            $score = $this->setupChangeCost($prevSetup, $this->setupFromCandidate($candidate));

            // Preferimos los que caben primero (para no depender de split)
            $fitsPenalty = $bins <= $remainingBins ? 0 : 1000;
            $score += $fitsPenalty;

            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $bestIdx = $idx;
                $bestCandidate = $candidate;
            }
        }

        return $bestCandidate ? [$bestIdx, $bestCandidate] : null;
    }

    private function setupFromCandidate(array $candidate): array
    {
        return [
            'nota_calidad' => $candidate['setup_nota_calidad'] ?? null,
            'calibre' => $candidate['setup_calibre'] ?? null,
            'variedad' => $candidate['variedad'] ?? null,
            'color' => $candidate['setup_color'] ?? null,
        ];
    }

    private function setupChangeCost(?array $prev, array $next): int
    {
        if ($prev === null) {
            return 0;
        }

        $costs = (array) config('planning.setup_costs', [
            'color' => 1,
            'calibre' => 3,
            'variedad' => 5,
            'nota_calidad' => 8,
        ]);

        $cost = 0;
        if (($prev['color'] ?? null) !== ($next['color'] ?? null)) {
            $cost += (int) ($costs['color'] ?? 1);
        }
        if (($prev['calibre'] ?? null) !== ($next['calibre'] ?? null)) {
            $cost += (int) ($costs['calibre'] ?? 3);
        }
        if (($prev['variedad'] ?? null) !== ($next['variedad'] ?? null)) {
            $cost += (int) ($costs['variedad'] ?? 5);
        }
        if (($prev['nota_calidad'] ?? null) !== ($next['nota_calidad'] ?? null)) {
            $cost += (int) ($costs['nota_calidad'] ?? 8);
        }

        return $cost;
    }

    private function normalizeKeyPart(string $value, bool $upper = true): string
    {
        $normalized = trim(Str::ascii($value));
        if ($normalized === '') {
            return '';
        }

        return $upper ? mb_strtoupper($normalized) : mb_strtolower($normalized);
    }

    private function proratePeso(?float $peso, int $takeBins, int $totalBins): ?float
    {
        if ($peso === null || $totalBins <= 0) {
            return $peso;
        }

        return round(((float) $peso) * ($takeBins / $totalBins), 3);
    }
}
