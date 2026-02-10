<?php

namespace App\Http\Controllers\Planning;

use App\Enums\PlanningProcessStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Http\Requests\Planning\StorePackingProcessBatchRequest;
use App\Models\Especie;
use App\Models\EstimationSeason;
use App\Models\PackingLine;
use App\Models\PackingProcess;
use App\Models\PackingProcessBatch;
use App\Models\PackingProcessLot;
use App\Models\Shift;
use App\Services\Planning\ProcessBatchGeneratorService;
use App\Services\Planning\ProcessConfirmationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PackingProcessBatchController extends Controller
{
    use AuthorizesPlanning;

    public function __construct(
        private readonly ProcessBatchGeneratorService $batchGenerator,
        private readonly ProcessConfirmationService $confirmationService,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizePlanning($request);

        $batches = PackingProcessBatch::query()
            ->with('shift')
            ->withCount('processes')
            ->orderByDesc('week_start')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Planning/Batches/Index', [
            'batches' => $batches,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizePlanning($request);

        $especies = Especie::query()->orderBy('name')->pluck('name')->values();
        $shifts = Shift::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'horas', 'hora_inicio']);
        $lines = PackingLine::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo', 'especie', 'especies']);

        $weekStart = $request->query('week_start') ? Carbon::parse((string) $request->query('week_start')) : now();
        $days = (int) ($request->query('days') ?? 7);
        $days = max(1, min(14, $days));
        $weekEnd = (clone $weekStart)->startOfDay()->addDays($days - 1);

        $estimationSpecies = $this->getBiweeklyEstimatedSpeciesForRange($weekStart->startOfDay(), $weekEnd);

        return Inertia::render('Planning/Batches/Create', [
            'especies' => $especies,
            'shifts' => $shifts,
            'lines' => $lines,
            'estimationSpecies' => $estimationSpecies,
            'defaults' => [
                'especie' => $request->query('especie'),
                'week_start' => $request->query('week_start'),
                'shift_id' => $request->query('shift_id'),
            ],
        ]);
    }

    public function estimationSpecies(Request $request)
    {
        $this->authorizePlanning($request);

        $weekStart = $request->query('week_start') ? Carbon::parse((string) $request->query('week_start')) : now();
        $days = (int) ($request->query('days') ?? 7);
        $days = max(1, min(14, $days));
        $weekEnd = (clone $weekStart)->startOfDay()->addDays($days - 1);

        return response()->json([
            'data' => $this->getBiweeklyEstimatedSpeciesForRange($weekStart->startOfDay(), $weekEnd),
        ]);
    }

    public function store(StorePackingProcessBatchRequest $request)
    {
        $this->authorizePlanning($request);

        $days = (int) ($request->input('days') ?? 7);
        $days = max(1, min(14, $days));

        $weekStart = Carbon::parse((string) $request->input('week_start'))->startOfDay();
        $weekEnd = (clone $weekStart)->addDays($days - 1);

        $requestedEspecie = trim((string) ($request->input('especie') ?? ''));
        if ($requestedEspecie === '__ALL__') {
            $requestedEspecie = '';
        }
        $multiSpecies = $requestedEspecie === '';

        $batch = DB::transaction(function () use ($request, $weekStart, $weekEnd, $requestedEspecie, $multiSpecies) {
            $speciesList = $multiSpecies
                ? Especie::query()->orderBy('name')->pluck('name')->values()->all()
                : [$requestedEspecie];

            // Filtramos a especies "planificables": que tengan al menos una línea activa configurada.
            $speciesList = collect($speciesList)->filter(function ($especie) {
                $especie = trim((string) $especie);
                if ($especie === '') {
                    return false;
                }
                return PackingLine::query()->where('activo', true)->forEspecie($especie)->exists();
            })->values()->all();

            // Filtramos a especies con estimación (si no existe estimación, no se incluye en el batch).
            $seasonId = (int) (EstimationSeason::query()
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->value('id') ?? 0);

            if ($seasonId > 0) {
                $speciesList = collect($speciesList)->filter(function ($especie) use ($seasonId, $weekStart, $weekEnd) {
                    return $this->hasBiweeklyEstimationForSpecies($seasonId, $weekStart, $weekEnd, (string) $especie);
                })->values()->all();
            }

            if (empty($speciesList)) {
                abort(422, 'No hay especies con líneas/cámaras activas y estimación para planificar.');
            }

            $batch = PackingProcessBatch::create([
                // null = batch multi-especie
                'especie' => $multiSpecies ? null : $requestedEspecie,
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'shift_id' => $request->integer('shift_id'),
                'estado' => PlanningProcessStatus::BORRADOR,
                'creado_por' => $request->user()?->id,
                'included_packing_line_ids' => $request->input('included_packing_line_ids') ?: null,
            ]);

            $cursor = $weekStart->copy();
            while ($cursor->lte($weekEnd)) {
                foreach ($speciesList as $especie) {
                    PackingProcess::create([
                        'process_batch_id' => $batch->id,
                        'especie' => (string) $especie,
                        'fecha' => $cursor->toDateString(),
                        'shift_id' => $request->integer('shift_id'),
                        'extra_horas' => 0,
                        'estado' => PlanningProcessStatus::BORRADOR,
                        'creado_por' => $request->user()?->id,
                        'included_packing_line_ids' => $request->input('included_packing_line_ids') ?: null,
                    ]);
                }
                $cursor->addDay();
            }

            return $batch;
        });

        if ($request->boolean('auto_generate', true)) {
            $this->batchGenerator->generateWeek($batch);
        }

        return redirect()->route('planning.batches.show', $batch)->with('success', 'Plan semanal creado. Puedes ajustar cada día y confirmar.');
    }

    public function show(Request $request, PackingProcessBatch $batch)
    {
        $this->authorizePlanning($request);

        $batch->load(['shift', 'processes.shift', 'processes.lineOverrides']);

        $processesById = $batch->processes
            ->sortBy([['fecha', 'asc'], ['especie', 'asc'], ['id', 'asc']])
            ->values()
            ->keyBy('id');

        $processIds = $processesById->keys()->values()->all();

        $extraByProcessLine = [];
        foreach ($processesById as $p) {
            $extraByProcessLine[(int) $p->id] = ($p->lineOverrides ?? collect())
                ->mapWithKeys(fn ($r) => [(int) $r->packing_line_id => (float) $r->extra_horas])
                ->all();
        }

        $lots = collect();
        if (! empty($processIds)) {
            $lots = PackingProcessLot::query()
                ->whereIn('process_id', $processIds)
                ->with(['packingLine:id,nombre,tipo'])
                ->orderBy('process_id')
                ->orderBy('packing_line_id')
                ->orderByRaw('CASE WHEN inicio_estimado IS NULL THEN 1 ELSE 0 END')
                ->orderBy('inicio_estimado')
                ->orderBy('orden')
                ->orderBy('id')
                ->get([
                    'id',
                    'process_id',
                    'packing_line_id',
                    'n_g_recepcion',
                    'estado',
                    'cantidad_bins',
                    'inicio_estimado',
                    'fin_estimado',
                    'n_variedad',
                    'n_productor',
                    'csg_productor',
                ]);
        }

        $lotsByProcess = $lots->groupBy('process_id');

        $processes = $processesById
            ->values()
            ->map(function (PackingProcess $p) use ($lotsByProcess) {
                $estado = $p->estado?->value ?? (string) $p->estado;
                $lotsFor = $lotsByProcess[(string) $p->id] ?? collect();
                $lotsCount = $lotsFor->count();
                $conflictsCount = $lotsFor->where('estado', 'CONFLICTO')->count();

            return [
                'id' => $p->id,
                'fecha' => optional($p->fecha)->toDateString(),
                'especie' => $p->especie,
                'shift' => [
                    'id' => $p->shift?->id,
                    'codigo' => $p->shift?->codigo,
                    'nombre' => $p->shift?->nombre,
                    'horas' => $p->shift?->horas,
                    'hora_inicio' => $p->shift?->hora_inicio,
                ],
                'estado' => $estado,
                'lots_count' => $lotsCount,
                'conflicts_count' => $conflictsCount,
            ];
        });

        // Gantt simple (por línea) para visualización rápida de la semana.
        // No reemplaza la edición: para ajustar, se sigue usando "Abrir" por día.
        $gantt = null;
        $weekStart = $batch->week_start ? Carbon::parse($batch->week_start)->startOfDay() : null;
        $weekEnd = $batch->week_end ? Carbon::parse($batch->week_end)->startOfDay() : null;
        if ($weekStart && $weekEnd && $weekEnd->greaterThanOrEqualTo($weekStart) && $lots->isNotEmpty()) {
            $days = [];
            foreach (CarbonPeriod::create($weekStart, $weekEnd) as $d) {
                $days[] = $d->toDateString();
            }

            $cells = [];
            $lineMeta = [];

            foreach ($lots as $lot) {
                $p = $processesById->get($lot->process_id);
                if (! $p || ! $p->fecha) {
                    continue;
                }
                $day = $p->fecha->toDateString();
                $lineId = (int) $lot->packing_line_id;
                $cellKey = $lineId.'|'.$day;
                $extraHorasLine = (float) (($extraByProcessLine[(int) $p->id][$lineId] ?? 0));

                if (! isset($lineMeta[$lineId])) {
                    $lineMeta[$lineId] = [
                        'id' => $lineId,
                        'nombre' => $lot->packingLine?->nombre ?? ('Línea '.$lineId),
                        'tipo' => $lot->packingLine?->tipo?->value ?? (string) ($lot->packingLine?->tipo ?? ''),
                    ];
                }

                if (! isset($cells[$cellKey])) {
                    $cells[$cellKey] = [
                        'line_id' => $lineId,
                        'date' => $day,
                        'shift_horas' => (float) ($p->shift?->horas ?? 0),
                        'shift_hora_inicio' => $p->shift?->hora_inicio ? (string) $p->shift->hora_inicio : null,
                        'max_extra_horas' => $extraHorasLine,
                        'items' => [],
                    ];
                } else {
                    $cells[$cellKey]['max_extra_horas'] = max(
                        (float) ($cells[$cellKey]['max_extra_horas'] ?? 0),
                        $extraHorasLine
                    );
                }

                $cells[$cellKey]['items'][] = [
                    'id' => $lot->id,
                    'process_id' => $lot->process_id,
                    'especie' => $p->especie,
                    'n_g_recepcion' => $lot->n_g_recepcion,
                    'estado' => (string) ($lot->estado?->value ?? $lot->estado),
                    'cantidad_bins' => (int) ($lot->cantidad_bins ?? 0),
                    'inicio_estimado' => $lot->inicio_estimado,
                    'fin_estimado' => $lot->fin_estimado,
                    'n_variedad' => $lot->n_variedad,
                    'n_productor' => $lot->n_productor,
                    'csg_productor' => $lot->csg_productor,
                ];
            }

            $gantt = [
                'days' => $days,
                'lines' => array_values($lineMeta),
                'cells' => array_values($cells),
            ];
        }

        return Inertia::render('Planning/Batches/Show', [
            'batch' => [
                'id' => $batch->id,
                'especie' => $batch->especie,
                'week_start' => optional($batch->week_start)->toDateString(),
                'week_end' => optional($batch->week_end)->toDateString(),
                'estado' => $batch->estado?->value ?? (string) $batch->estado,
                'shift' => [
                    'id' => $batch->shift?->id,
                    'codigo' => $batch->shift?->codigo,
                    'nombre' => $batch->shift?->nombre,
                    'horas' => $batch->shift?->horas,
                    'hora_inicio' => $batch->shift?->hora_inicio,
                ],
            ],
            'processes' => $processes,
            'gantt' => $gantt,
        ]);
    }

    public function generate(Request $request, PackingProcessBatch $batch)
    {
        $this->authorizePlanning($request);

        $this->batchGenerator->generateWeek($batch);

        return back()->with('success', 'Propuesta semanal generada. Abre un día para ajustar.');
    }

    public function confirm(Request $request, PackingProcessBatch $batch)
    {
        $this->authorizePlanning($request);

        $batch->load('processes');

        $ok = true;
        $conflicted = 0;
        $created = 0;
        foreach ($batch->processes->sortBy('fecha') as $process) {
            $result = $this->confirmationService->finalizeAndConfirm($process);
            $created += count($result['created_process_ids'] ?? []);

            if (! ($result['ok'] ?? false)) {
                $ok = false;
                $conflicted += max(1, count($result['conflicts'] ?? []));
            }
        }

        if (! $ok) {
            return back()->with('error', "Semana procesada. Procesos generados: {$created}. Conflictos detectados: {$conflicted}. Abre cada día/proceso para resolver y vuelve a confirmar.");
        }

        return back()->with('success', "Semana confirmada (sin conflictos). Procesos generados: {$created}. Puedes imprimir por día.");
    }

    private function hasBiweeklyEstimationForSpecies(int $seasonId, Carbon $from, Carbon $to, string $especie): bool
    {
        $needle = mb_strtolower(trim($especie));
        $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
        $needle = preg_replace('/\s+/', ' ', (string) $needle);
        $needle = trim((string) $needle);
        $needle = preg_replace('/s$/', '', (string) $needle);
        $needle = mb_substr((string) $needle, 0, 7);

        return DB::table('estimation_biweekly_rows as r')
            ->join('estimation_biweekly_versions as v', 'v.id', '=', 'r.estimation_biweekly_version_id')
            ->where('v.season_id', $seasonId)
            ->where('v.status', 'active')
            ->whereNotNull('r.dia')
            ->whereBetween('r.dia', [$from->toDateString(), $to->toDateString()])
            ->whereRaw('lower(ltrim(rtrim(coalesce(r.especie, \'\')))) like ?', ['%'.$needle.'%'])
            ->exists();
    }

    /**
     * Especies con estimación bisemanal ACTIVE dentro del rango (y con líneas activas configuradas).
     *
     * @return array<int,string>
     */
    private function getBiweeklyEstimatedSpeciesForRange(Carbon $from, Carbon $to): array
    {
        $seasonId = (int) (EstimationSeason::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $from->toDateString())
            ->where('end_date', '>=', $to->toDateString())
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        if ($seasonId <= 0) {
            $seasonId = (int) (EstimationSeason::query()->orderByDesc('is_active')->orderByDesc('id')->value('id') ?? 0);
        }

        if ($seasonId <= 0) {
            return [];
        }

        $known = Especie::query()->orderBy('name')->pluck('name')->values()->all();
        if (empty($known)) {
            return [];
        }

        // Igual que los repos: matching tolerante por "needle" (primeros 7 chars normalizados),
        // porque a veces estimación viene como NECTARIN vs Nectarines, etc.
        $knownNorm = [];
        foreach ($known as $name) {
            $norm = $this->normalizeSpeciesNeedle((string) $name);
            if ($norm !== '' && ! isset($knownNorm[$norm])) {
                $knownNorm[$norm] = (string) $name;
            }
        }

        $raw = DB::table('estimation_biweekly_rows as r')
            ->join('estimation_biweekly_versions as v', 'v.id', '=', 'r.estimation_biweekly_version_id')
            ->where('v.season_id', $seasonId)
            ->where('v.status', 'active')
            ->whereNotNull('r.dia')
            ->whereBetween('r.dia', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('r.especie')
            ->selectRaw('distinct r.especie as especie')
            ->get();

        $estNorm = [];
        foreach ($raw as $row) {
            $norm = $this->normalizeSpeciesNeedle((string) ($row->especie ?? ''));
            if ($norm !== '') {
                $estNorm[$norm] = true;
            }
        }

        $matched = [];
        foreach (array_keys($estNorm) as $norm) {
            if (isset($knownNorm[$norm])) {
                $matched[] = $knownNorm[$norm];
            }
        }

        // Solo las que tienen líneas activas
        $matched = collect($matched)->filter(function ($especie) {
            return PackingLine::query()->where('activo', true)->forEspecie((string) $especie)->exists();
        })->values()->all();

        sort($matched);
        return $matched;
    }

    private function normalizeSpeciesNeedle(string $value): string
    {
        $s = trim($value);
        if ($s === '') {
            return '';
        }
        $s = mb_strtolower($s);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/\s+/', ' ', (string) $s);
        $s = trim((string) $s);
        if (strlen($s) > 4) {
            $s = preg_replace('/s$/', '', (string) $s);
        }
        $s = mb_substr((string) $s, 0, 7);
        return (string) $s;
    }
}
