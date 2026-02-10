<?php

namespace App\Services\Planning;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FruitFlowMatrixService
{
    public function __construct(
        private readonly BiweeklyEstimationRepositoryMysql $estimations,
        private readonly InventoryRepositorySqlsrv $inventory,
        private readonly ProcessedLotsRepositorySqlsrv $processed,
    ) {
    }

    /**
     * Matriz operacional (3 pestañas) para 3 semanas: anterior, actual y siguiente.
     *
     * Row key: especie|variedad|mx(0|1)
     */
    public function build(array $filters): array
    {
        $seasonId = (int) ($filters['season_id'] ?? 0);
        $especie = ! empty($filters['especie']) ? (string) $filters['especie'] : null;
        $variedad = ! empty($filters['variedad']) ? (string) $filters['variedad'] : null;
        $producerQ = ! empty($filters['producer_q']) ? (string) $filters['producer_q'] : null;

        $weekAnchor = Carbon::parse($filters['anchor'] ?? now()->toDateString());
        // ISO-like: start week Monday.
        $start = (clone $weekAnchor)->startOfWeek(Carbon::MONDAY)->subWeek();
        $end = (clone $weekAnchor)->endOfWeek(Carbon::SUNDAY)->addWeek();

        $days = $this->buildDays($start, $end);
        $weeks = $this->groupWeeks($days);

        $onlyActive = (bool) ($filters['only_active_producers'] ?? true);

        $weekNumbers = collect($days)->map(fn ($d) => (int) $d['week'])->unique()->values()->all();
        $weekVersionMap = $seasonId > 0 ? $this->estimations->getActiveVersionIdsForWeeks($seasonId, $weekNumbers) : [];
        $estimationVersionIdsUsed = collect($weekVersionMap)->values()->filter()->unique()->values()->all();

        $estimationRows = $seasonId > 0
            ? $this->estimations->getDailyKilos($seasonId, $start, $end, [
                'only_active_producers' => $onlyActive,
                'especie' => $especie,
                'variedad' => $variedad,
                'producer_q' => $producerQ,
            ])
            : collect();

        // Pestaña "Existencias" se alimenta desde SQLSRV (stock actual), usando `fecha_recepcion`
        // para posicionar en el calendario.
        $producerMap = $this->buildProducerMap($onlyActive);
        $inventoryLots = $this->inventory->getAvailableLots([
            'limit' => (int) ($filters['inventory_limit'] ?? 5000),
            'especie' => $especie,
            'variedad' => $variedad,
            'productor_q' => $producerQ,
            'fecha_from' => $start->toDateString(),
            'fecha_to' => $end->toDateString(),
        ]);

        $inventoryRows = $this->toDailyKilosFromInventoryLots($inventoryLots, $producerMap, $onlyActive);

        $processedRowsRaw = $this->processed->getDailyKilos($start, $end, [
            'especie' => $especie,
            'variedad' => $variedad,
        ]);
        Log::debug('Raw processed rows', [$processedRowsRaw]);
        // Procesado: filtramos por productor (si aplica) y solo activos (si aplica), pero la matriz se agrupa
        // por especie+variedad, no por productor.
        $processedRows = $processedRowsRaw
            ->filter(function (array $row) use ($onlyActive, $producerQ, $producerMap) {
                $rawProducerName = $row['producer_name'] ?? null;
                $rawCsg = $row['c_productor'] ?? null;

                $csgKey = $rawCsg ? mb_strtolower(trim((string) $rawCsg)) : '';
                $nameKey = $rawProducerName ? mb_strtolower(trim((string) $rawProducerName)) : '';

                $producer = $csgKey !== '' ? ($producerMap['by_csg'][$csgKey] ?? null) : null;
                if (! $producer && $nameKey !== '') {
                    $producer = $producerMap['by_name'][$nameKey] ?? null;
                }

                if ($producerQ !== null && $producerQ !== '') {
                    $needle = mb_strtolower(trim($producerQ));
                    $hay = $producer ? mb_strtolower(trim((string) ($producer['name'] ?? ''))) : $nameKey;
                    if ($hay === '' || ! str_contains($hay, $needle)) {
                        return false;
                    }
                }

                if (! $onlyActive) {
                    return true;
                }

                return $producer !== null && ($producer['is_active'] ?? false) === true;
            })
            ->map(function (array $row) {
                return [
                    ...$row,
                    // Normalizamos a la misma forma que la matriz espera
                    'especie' => $row['especie'] ?? ($row['n_especie'] ?? null),
                    'variedad' => $row['variedad'] ?? ($row['n_variedad'] ?? null),
                ];
            })
            ->values();

        $rowsMeta = $this->buildRowsMeta([$estimationRows, $inventoryRows, $processedRows]);

        // Build matrices per tab.
        $estimation = $this->buildMatrix($rowsMeta, $days, $estimationRows, 'kilos');
        $reception = $this->buildMatrix($rowsMeta, $days, $inventoryRows, 'kilos');
        $processed = $this->buildMatrix($rowsMeta, $days, $processedRows, 'kilos');
        Log::debug('processed', ['data' => $processed]);

        // Badges/flags para pestaña "Recepción" (solo visuales).
        // - mexico: desde estimación bisemanal (solo días futuros, porque se muestran como overlay).
        // - mosca: desde estimaciones principales (radio_mosca) de la temporada (últimas ACTIVE).
        $today = now()->toDateString();
        $receptionBadges = [];

        foreach ($estimationRows as $r) {
            $day = (string) ($r['dia'] ?? '');
            if ($day === '' || $day <= $today) {
                continue;
            }
            if (! ($r['mexico'] ?? false)) {
                continue;
            }
            $rk = $this->rowKey(
                (string) ($r['especie'] ?? ''),
                (string) ($r['variedad'] ?? ''),
                true
            );
            $receptionBadges[$rk] = [
                ...($receptionBadges[$rk] ?? []),
                'mexico' => true,
            ];
        }

        if ($seasonId > 0) {
            $moscaMap = $this->getMoscaFlagsForSeason($seasonId, [
                'only_active_producers' => $onlyActive,
                'especie' => $especie,
                'variedad' => $variedad,
                'producer_q' => $producerQ,
            ]);
            foreach ($moscaMap as $rk => $flag) {
                if (! $flag) {
                    continue;
                }
                // Mosca aplica a la combinación especie+variedad, independiente de México.
                foreach ([$this->rowKeyFromBase($rk, false), $this->rowKeyFromBase($rk, true)] as $fullKey) {
                    $receptionBadges[$fullKey] = [
                        ...($receptionBadges[$fullKey] ?? []),
                        'mosca' => true,
                    ];
                }
            }
        }

        return [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'anchor' => $weekAnchor->toDateString(),
            ],
            'days' => $days,
            'weeks' => $weeks,
            'rows' => $rowsMeta,
            'tabs' => [
                'estimation' => [
                    'title' => 'Estimación bisemanal',
                    'version_ids' => $estimationVersionIdsUsed,
                    'week_version_map' => $weekVersionMap,
                    ...$estimation,
                ],
                'reception' => [
                    'title' => 'Existencias (stock)',
                    'row_badges' => $receptionBadges,
                    ...$reception,
                ],
                'processed' => [
                    'title' => 'Lotes procesados',
                    ...$processed,
                ],
            ],
        ];
    }

    private function buildProducerMap(bool $onlyActive): array
    {
        $query = User::role('Productor')->select(['id', 'name', 'csg', 'is_active']);
        if ($onlyActive) {
            $query->where('is_active', true);
        }
        $producers = $query->get();

        $byCsg = [];
        $byName = [];
        foreach ($producers as $p) {
            $nameKey = mb_strtolower(trim((string) $p->name));
            if ($nameKey !== '') {
                $byName[$nameKey] = ['id' => $p->id, 'name' => $p->name, 'is_active' => $p->is_active];
            }
            $csgKey = mb_strtolower(trim((string) ($p->csg ?? '')));
            if ($csgKey !== '') {
                $byCsg[$csgKey] = ['id' => $p->id, 'name' => $p->name, 'is_active' => $p->is_active];
            }
        }

        return ['by_csg' => $byCsg, 'by_name' => $byName];
    }

    /**
     * @param \Illuminate\Support\Collection<int,array<string,mixed>> $inventoryLots
     * @param array{by_csg:array<string,array{id:int,name:string,is_active:bool}>,by_name:array<string,array{id:int,name:string,is_active:bool}>} $producerMap
     */
    private function toDailyKilosFromInventoryLots(Collection $inventoryLots, array $producerMap, bool $onlyActive): Collection
    {
        $bucket = [];

        foreach ($inventoryLots as $lot) {
            $rawDate = $lot['fecha_recepcion'] ?? null;
            if (! $rawDate) {
                continue;
            }
            try {
                $day = Carbon::parse($rawDate)->toDateString();
            } catch (\Throwable) {
                continue;
            }

            $especie = $lot['especie'] ?? ($lot['n_especie'] ?? null);
            $variedad = $lot['variedad'] ?? ($lot['n_variedad'] ?? null);

            $csg = $lot['csg_productor'] ? mb_strtolower(trim((string) $lot['csg_productor'])) : '';
            $name = $lot['n_productor'] ? mb_strtolower(trim((string) $lot['n_productor'])) : ($lot['productor'] ? mb_strtolower(trim((string) $lot['productor'])) : '');

            $producer = $csg !== '' ? ($producerMap['by_csg'][$csg] ?? null) : null;
            if (! $producer && $name !== '') {
                $producer = $producerMap['by_name'][$name] ?? null;
            }

            if ($onlyActive) {
                if (! $producer || ($producer['is_active'] ?? false) !== true) {
                    continue;
                }
            }

            $kilos = (float) ($lot['peso_neto'] ?? 0);
            if ($kilos <= 0) {
                continue;
            }

            $key = $this->rowKey((string) ($especie ?? ''), (string) ($variedad ?? ''), false).'|'.$day;

            if (! isset($bucket[$key])) {
                $bucket[$key] = [
                    'especie' => $especie,
                    'variedad' => $variedad,
                    'mexico' => false,
                    'dia' => $day,
                    'kilos' => 0.0,
                ];
            }
            $bucket[$key]['kilos'] += $kilos;
        }

        return collect(array_values($bucket));
    }

    private function buildDays(Carbon $start, Carbon $end): array
    {
        $today = now()->toDateString();
        $days = [];
        $cursor = (clone $start);
        while ($cursor->lte($end)) {
            $days[] = [
                'date' => $cursor->toDateString(),
                'dow' => (int) $cursor->dayOfWeekIso, // 1..7
                'label' => $cursor->format('d/m'),
                'week' => $cursor->isoWeek(),
                'week_year' => $cursor->isoWeekYear(),
                'is_today' => $cursor->toDateString() === $today,
            ];
            $cursor->addDay();
        }
        return $days;
    }

    private function groupWeeks(array $days): array
    {
        $weeks = [];
        foreach ($days as $d) {
            $key = $d['week_year'].'-W'.str_pad((string) $d['week'], 2, '0', STR_PAD_LEFT);
            if (! isset($weeks[$key])) {
                $weeks[$key] = [
                    'key' => $key,
                    'week_year' => $d['week_year'],
                    'week' => $d['week'],
                    'days' => [],
                ];
            }
            $weeks[$key]['days'][] = $d['date'];
        }

        // Add ranges
        return array_values(array_map(function ($w) {
            $first = $w['days'][0] ?? null;
            $last = $w['days'][count($w['days']) - 1] ?? null;
            return [
                ...$w,
                'range_label' => $first && $last ? ($first.' → '.$last) : null,
            ];
        }, $weeks));
    }

    /**
     * @param array<int, Collection> $sources
     */
    private function buildRowsMeta(array $sources): array
    {
        $rows = collect();
        foreach ($sources as $src) {
            $rows = $rows->concat($src);
        }

        $rows = $rows
            ->map(function (array $r) {
                $especie = $r['especie'] !== null ? trim((string) $r['especie']) : '';
                $variedad = $r['variedad'] !== null ? trim((string) $r['variedad']) : '';
                $mexico = (bool) ($r['mexico'] ?? false);
                return [
                    'key' => $this->rowKey($especie, $variedad, $mexico),
                    'especie' => $especie,
                    'variedad' => $variedad,
                    'mexico' => $mexico,
                ];
            })
            ->unique('key')
            ->sortBy([
                ['especie', 'asc'],
                ['variedad', 'asc'],
                // México primero dentro de la misma variedad, para que sea visible.
                ['mexico', 'desc'],
            ])
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @param array<int, array> $rowsMeta
     * @param array<int, array> $days
     */
    private function buildMatrix(array $rowsMeta, array $days, Collection $rows, string $valueField): array
    {
        $dayKeys = array_map(fn ($d) => $d['date'], $days);

        $cells = [];
        $totalsByDay = array_fill_keys($dayKeys, 0.0);
        $rowTotals = [];

        foreach ($rowsMeta as $meta) {
            $rowKey = $meta['key'];
            $cells[$rowKey] = array_fill_keys($dayKeys, 0.0);
            $rowTotals[$rowKey] = 0.0;
        }

        foreach ($rows as $r) {
            $especie = $r['especie'] !== null ? trim((string) $r['especie']) : '';
            $variedad = $r['variedad'] !== null ? trim((string) $r['variedad']) : '';
            $rowKey = $this->rowKey($especie, $variedad, (bool) ($r['mexico'] ?? false));
            $day = (string) ($r['dia'] ?? '');
            if (! isset($cells[$rowKey]) || ! isset($cells[$rowKey][$day])) {
                continue;
            }
            $val = (float) ($r[$valueField] ?? 0);
            $cells[$rowKey][$day] += $val;
            $rowTotals[$rowKey] += $val;
            $totalsByDay[$day] += $val;
        }

        $grandTotal = array_sum($totalsByDay);

        return [
            'cells' => $cells,
            'totals_by_day' => $totalsByDay,
            'row_totals' => $rowTotals,
            'grand_total' => $grandTotal,
        ];
    }

    private function normalizeKeyPart(string $value, bool $singularize = true): string
    {
        $s = trim($value);
        if ($s === '') {
            return '';
        }

        $s = mb_strtolower($s);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim((string) $s);

        if ($singularize && strlen($s) > 4) {
            // Básico: CIRUELAS -> ciruela, NECTARINES -> nectarine, etc.
            $s = preg_replace('/s$/', '', $s);
        }

        return $s;
    }

    private function rowKey(string $especie, string $variedad, bool $mexico): string
    {
        return $this->normalizeKeyPart($especie)
            .'|'.$this->normalizeKeyPart($variedad, false)
            .'|mx'.($mexico ? '1' : '0');
    }

    /**
     * @param string $baseKey especie|variedad
     */
    private function rowKeyFromBase(string $baseKey, bool $mexico): string
    {
        return $baseKey.'|mx'.($mexico ? '1' : '0');
    }

    /**
     * Flags por especie+variedad desde "Estimaciones" (no bisemanal).
     *
     * Marca true si existe al menos una fila con radio_mosca=1 en alguna versión ACTIVE
     * de la temporada (cualquier tipo distinto de BISEMANAL).
     *
     * @return array<string,bool> map rowKey(especie|variedad) => has_mosca
     */
    private function getMoscaFlagsForSeason(int $seasonId, array $filters = []): array
    {
        $query = DB::table('estimation_rows as r')
            ->join('estimation_versions as v', 'v.id', '=', 'r.estimation_version_id')
            ->join('users as u', 'u.id', '=', 'r.producer_id')
            ->join('variedads as va', 'va.id', '=', 'r.variedad_id')
            ->leftJoin('especies as e', 'e.id', '=', 'va.especie_id')
            ->where('v.season_id', $seasonId)
            ->where('v.status', 'active')
            ->where('v.type', '!=', 'bisemanal');

        if (! empty($filters['only_active_producers'])) {
            $query->where('u.is_active', true);
        }

        if (! empty($filters['especie'])) {
            $needle = mb_strtolower(trim((string) $filters['especie']));
            $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
            $needle = preg_replace('/\s+/', ' ', (string) $needle);
            $needle = trim((string) $needle);
            $needle = preg_replace('/s$/', '', (string) $needle);
            $needle = mb_substr((string) $needle, 0, 7);
            $query->whereRaw('lower(ltrim(rtrim(coalesce(e.name, \'\')))) like ?', ['%'.$needle.'%']);
        }

        if (! empty($filters['variedad'])) {
            $needle = mb_strtolower(trim((string) $filters['variedad']));
            $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
            $needle = preg_replace('/\s+/', ' ', (string) $needle);
            $needle = trim((string) $needle);
            $needle = mb_substr((string) $needle, 0, 12);
            $query->whereRaw('lower(ltrim(rtrim(coalesce(va.name, \'\')))) like ?', ['%'.$needle.'%']);
        }

        if (! empty($filters['producer_q'])) {
            $needle = '%'.mb_strtolower(trim((string) $filters['producer_q'])).'%';
            $query->whereRaw('lower(coalesce(u.name, \'\')) like ?', [$needle]);
        }

        $rows = $query
            ->selectRaw('
                max(e.name) as especie,
                max(va.name) as variedad,
                max(case when coalesce(r.radio_mosca, 0) = 1 then 1 else 0 end) as has_mosca
            ')
            ->groupBy('e.name', 'va.name')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $rk = $this->normalizeKeyPart((string) ($row->especie ?? ''))
                .'|'.$this->normalizeKeyPart((string) ($row->variedad ?? ''), false);
            $map[$rk] = ((int) ($row->has_mosca ?? 0)) === 1;
        }

        return $map;
    }
}
