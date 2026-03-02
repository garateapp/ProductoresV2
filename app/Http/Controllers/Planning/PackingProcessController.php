<?php

namespace App\Http\Controllers\Planning;

use App\Enums\PlanningProcessStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Http\Requests\Planning\StorePackingProcessRequest;
use App\Http\Requests\Planning\UpdatePackingProcessLotsRequest;
use App\Models\Especie;
use App\Models\EstimationSeason;
use App\Models\PackingLine;
use App\Models\PackingProcess;
use App\Models\PackingProcessLot;
use App\Models\PackingProcessLotPackagingChange;
use App\Models\PackagingMatrixRule;
use App\Models\PlanningInstructionVersion;
use App\Models\Recepcion;
use App\Models\ProducerCsg;
use App\Models\Shift;
use App\Models\User;
use App\Models\Variedad;
use App\Services\Planning\CapacityResolverService;
use App\Services\Planning\InventoryRepositorySqlsrv;
use App\Services\Planning\ProcessConfirmationService;
use App\Services\Planning\ProcessGeneratorService;
use App\Services\Planning\QualityRepositoryMysql;
use App\Services\Planning\CarozosPackagingMatrixService;
use App\Services\Planning\PackagingRepositorySqlsrv;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html as SpreadsheetHtmlWriter;
use Spatie\Browsershot\Browsershot;
use Illuminate\Validation\ValidationException;

class PackingProcessController extends Controller
{
    use AuthorizesPlanning;

    public function __construct(
        private readonly InventoryRepositorySqlsrv $inventoryRepository,
        private readonly QualityRepositoryMysql $qualityRepository,
        private readonly ProcessGeneratorService $generator,
        private readonly ProcessConfirmationService $confirmationService,
        private readonly CapacityResolverService $capacityResolver,
        private readonly CarozosPackagingMatrixService $carozosPackagingMatrix,
        private readonly PackagingRepositorySqlsrv $packagingRepository,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorizePlanning($request);

        $query = PackingProcess::query()
            ->with('shift')
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($request->filled('especie')) {
            $query->where('especie', (string) $request->input('especie'));
        }

        // Operación en planta: preferimos mostrar el día completo por línea/turno.
        // Si se pagina muy bajo, una misma línea puede quedar "partida" entre páginas.
        $processes = $query->paginate(200)->withQueryString();

        // Resumen operacional (para Index): 1er lote del proceso (si existe),
        // con productor/variedad/CSG/SDP. Es una ayuda visual rápida; el detalle
        // completo está en la pantalla del proceso.
        $processIds = $processes->getCollection()->pluck('id')->values()->all();
        $firstLotsByProcess = collect();
        $sdpByKey = [];
        $sqlsrvSdpMaps = ['triple' => [], 'pair' => []];
        $linesByProcess = [];
        $lineTimesByProcess = [];
        $lineLotsByProcess = [];
        $manualLineOrderByProcess = [];

        if (! empty($processIds)) {
            // Líneas involucradas por proceso (para agrupar en Index y permitir “Imprimir por línea”).
            $lineRows = DB::table('process_lots as pl')
                ->join('packing_lines as l', 'pl.packing_line_id', '=', 'l.id')
                ->whereIn('pl.process_id', $processIds)
                ->select('pl.process_id', 'l.id as line_id', 'l.nombre as line_nombre')
                ->distinct()
                ->orderBy('pl.process_id')
                ->orderBy('l.nombre')
                ->get();

            foreach ($lineRows as $row) {
                $pid = (int) ($row->process_id ?? 0);
                $lid = (int) ($row->line_id ?? 0);
                if ($pid <= 0 || $lid <= 0) {
                    continue;
                }
                if (! array_key_exists($pid, $linesByProcess)) {
                    $linesByProcess[$pid] = [];
                }
                $linesByProcess[$pid][] = [
                    'id' => $lid,
                    'nombre' => (string) ($row->line_nombre ?? ('Línea '.$lid)),
                ];
            }

            // Resumen de tiempo/cantidad por (proceso, línea) para vista tipo Gantt en Index.
            $timeRows = DB::table('process_lots as pl')
                ->whereIn('pl.process_id', $processIds)
                ->selectRaw('
                    pl.process_id,
                    pl.packing_line_id,
                    min(pl.inicio_estimado) as inicio_estimado,
                    max(pl.fin_estimado) as fin_estimado,
                    timestampdiff(minute, min(pl.inicio_estimado), max(pl.fin_estimado)) as duration_minutes,
                    sum(coalesce(pl.cantidad_bins,0)) as bins,
                    sum(coalesce(pl.peso_neto,0)) as kilos
                ')
                ->groupBy('pl.process_id', 'pl.packing_line_id')
                ->orderBy('pl.process_id')
                ->get();

            foreach ($timeRows as $row) {
                $pid = (int) ($row->process_id ?? 0);
                $lid = (int) ($row->packing_line_id ?? 0);
                if ($pid <= 0 || $lid <= 0) {
                    continue;
                }
                if (! isset($lineTimesByProcess[$pid])) {
                    $lineTimesByProcess[$pid] = [];
                }
                $lineTimesByProcess[$pid][$lid] = [
                    'inicio_estimado' => $row->inicio_estimado ? (string) $row->inicio_estimado : null,
                    'fin_estimado' => $row->fin_estimado ? (string) $row->fin_estimado : null,
                    'duration_minutes' => $row->duration_minutes !== null ? (int) $row->duration_minutes : null,
                    'bins' => isset($row->bins) ? (int) $row->bins : 0,
                    'kilos' => isset($row->kilos) ? (float) $row->kilos : 0.0,
                ];
            }

            $lots = PackingProcessLot::query()
                ->whereIn('process_id', $processIds)
                ->orderBy('process_id')
                ->orderByRaw('CASE WHEN inicio_estimado IS NULL THEN 1 ELSE 0 END')
                ->orderBy('inicio_estimado')
                ->orderBy('orden')
                ->orderBy('id')
                ->get([
                    'id',
                    'process_id',
                    'packing_line_id',
                    'orden',
                    'n_g_recepcion',
                    'n_productor',
                    'n_variedad',
                    'csg_productor',
                    'sdp_centrocosto',
                ]);

            foreach ($lots as $lot) {
                if ($firstLotsByProcess->has($lot->process_id)) {
                    continue;
                }
                $firstLotsByProcess->put($lot->process_id, $lot);
            }

            $processSpeciesById = $processes->getCollection()
                ->mapWithKeys(fn (PackingProcess $process) => [(int) $process->id => trim((string) ($process->especie ?? ''))]);

            $csgCodes = $lots
                ->pluck('csg_productor')
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->map(fn ($v) => trim($v))
                ->unique()
                ->values()
                ->all();

            $sqlsrvLookups = [];
            foreach ($lots as $lot) {
                $processId = (int) ($lot->process_id ?? 0);
                $csg = trim((string) ($lot->csg_productor ?? ''));
                $variedad = trim((string) ($lot->n_variedad ?? ''));
                $especie = trim((string) ($processSpeciesById[$processId] ?? ''));
                if ($csg === '' || $variedad === '' || $especie === '') {
                    continue;
                }
                $sqlsrvLookups[] = [
                    'csg' => $csg,
                    'especie' => $especie,
                    'variedad' => $variedad,
                ];
            }

            $sqlsrvSdpMaps = $this->resolveSqlsrvSdpMaps($sqlsrvLookups);

            $variedades = $lots
                ->pluck('n_variedad')
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->map(fn ($v) => trim($v))
                ->unique()
                ->values()
                ->all();

            // Fallback local: si no hay SDP en SQL Server, intentamos con catálogo interno.
            if (! empty($csgCodes) && ! empty($variedades)) {
                $producerCsgRows = ProducerCsg::query()
                    ->whereIn('csg_code', $csgCodes)
                    ->whereIn('variedad', $variedades)
                    ->get(['csg_code', 'variedad', 'sdp']);

                foreach ($producerCsgRows as $row) {
                    $key = $this->buildSdpPairKey(
                        trim((string) $row->csg_code),
                        trim((string) $row->variedad),
                    );
                    if (! array_key_exists($key, $sdpByKey) && ($row->sdp ?? null)) {
                        $sdpByKey[$key] = $row->sdp;
                    }
                }
            }

            foreach ($lots as $lot) {
                $processId = (int) ($lot->process_id ?? 0);
                $lineId = (int) ($lot->packing_line_id ?? 0);
                if ($processId <= 0 || $lineId <= 0) {
                    continue;
                }

                $csg = trim((string) ($lot->csg_productor ?? ''));
                $variedad = trim((string) ($lot->n_variedad ?? ''));
                $especie = trim((string) ($processSpeciesById[$processId] ?? ''));
                $tripleKey = $this->buildSdpTripleKey($csg, $especie, $variedad);
                $pairKey = $this->buildSdpPairKey($csg, $variedad);

                $sdp = trim((string) ($lot->sdp_centrocosto ?? ''));
                if ($sdp === '' && $tripleKey !== '' && isset($sqlsrvSdpMaps['triple'][$tripleKey])) {
                    $sdp = trim((string) $sqlsrvSdpMaps['triple'][$tripleKey]);
                }
                if ($sdp === '' && $pairKey !== '' && isset($sqlsrvSdpMaps['pair'][$pairKey])) {
                    $sdp = trim((string) $sqlsrvSdpMaps['pair'][$pairKey]);
                }
                if ($sdp === '' && $pairKey !== '' && isset($sdpByKey[$pairKey])) {
                    $sdp = trim((string) $sdpByKey[$pairKey]);
                }

                if (! isset($lineLotsByProcess[$processId])) {
                    $lineLotsByProcess[$processId] = [];
                }
                if (! isset($lineLotsByProcess[$processId][$lineId])) {
                    $lineLotsByProcess[$processId][$lineId] = [];
                }

                $lineLotsByProcess[$processId][$lineId][] = [
                    'id' => (int) $lot->id,
                    'orden' => (int) ($lot->orden ?? 0),
                    'n_g_recepcion' => $lot->n_g_recepcion ?: null,
                    'producer' => $lot->n_productor ?: null,
                    'variedad' => $lot->n_variedad ?: null,
                    'csg' => $lot->csg_productor ?: null,
                    'sdp' => $sdp !== '' ? $sdp : null,
                ];
            }

            foreach ($lineLotsByProcess as $processId => $byLine) {
                foreach ($byLine as $lineId => $items) {
                    usort($items, function (array $a, array $b) {
                        if (($a['orden'] ?? 0) !== ($b['orden'] ?? 0)) {
                            return (int) ($a['orden'] ?? 0) <=> (int) ($b['orden'] ?? 0);
                        }
                        return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
                    });
                    $lineLotsByProcess[$processId][$lineId] = array_values($items);
                }
            }

            if (Schema::hasTable('process_line_orders')) {
                $lineOrderRows = DB::table('process_line_orders as plo')
                    ->join('processes as p', 'p.id', '=', 'plo.process_id')
                    ->whereIn('plo.process_id', $processIds)
                    ->whereColumn('plo.fecha', 'p.fecha')
                    ->whereColumn('plo.shift_id', 'p.shift_id')
                    ->select('plo.process_id', 'plo.packing_line_id', 'plo.orden')
                    ->orderBy('plo.orden')
                    ->get();

                foreach ($lineOrderRows as $row) {
                    $pid = (int) ($row->process_id ?? 0);
                    $lid = (int) ($row->packing_line_id ?? 0);
                    $order = (int) ($row->orden ?? 0);
                    if ($pid <= 0 || $lid <= 0 || $order <= 0) {
                        continue;
                    }
                    if (! isset($manualLineOrderByProcess[$pid])) {
                        $manualLineOrderByProcess[$pid] = [];
                    }
                    $manualLineOrderByProcess[$pid][$lid] = $order;
                }
            }
        }

        $processes->setCollection(
            $processes->getCollection()->map(function (PackingProcess $process) use ($firstLotsByProcess, $sdpByKey, $linesByProcess, $lineTimesByProcess, $sqlsrvSdpMaps, $lineLotsByProcess, $manualLineOrderByProcess) {
                $lot = $firstLotsByProcess->get($process->id);
                $csg = $lot?->csg_productor ? trim((string) $lot->csg_productor) : null;
                $variedad = $lot?->n_variedad ? trim((string) $lot->n_variedad) : null;
                $especie = trim((string) ($process->especie ?? ''));
                $tripleKey = $this->buildSdpTripleKey($csg, $especie, $variedad);
                $pairKey = $this->buildSdpPairKey($csg, $variedad);
                $sqlsrvSdp = ($tripleKey !== '' && isset($sqlsrvSdpMaps['triple'][$tripleKey]))
                    ? $sqlsrvSdpMaps['triple'][$tripleKey]
                    : (($pairKey !== '' && isset($sqlsrvSdpMaps['pair'][$pairKey]))
                        ? $sqlsrvSdpMaps['pair'][$pairKey]
                        : null);

                $process->setAttribute('first_lot', $lot ? [
                    'n_g_recepcion' => $lot->n_g_recepcion ?: null,
                    'producer' => $lot->n_productor ?: null,
                    'variedad' => $lot->n_variedad ?: null,
                    'csg' => $lot->csg_productor ?: null,
                    'sdp' => $sqlsrvSdp ?: (($pairKey !== '' && isset($sdpByKey[$pairKey])) ? $sdpByKey[$pairKey] : null),
                ] : null);

                $lines = $linesByProcess[$process->id] ?? [];
                $process->setAttribute('lines', $lines);
                $process->setAttribute('primary_line', count($lines) === 1 ? $lines[0] : null);

                $process->setAttribute('line_times', $lineTimesByProcess[$process->id] ?? []);
                $process->setAttribute('line_lots', $lineLotsByProcess[$process->id] ?? []);
                $process->setAttribute('manual_line_order', $manualLineOrderByProcess[$process->id] ?? []);

                return $process;
            })
        );

        return Inertia::render('Planning/Processes/Index', [
            'processes' => $processes,
            'filters' => $request->only(['especie']),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizePlanning($request);

        $especies = Especie::query()->orderBy('name')->pluck('name')->values();
        $shifts = Shift::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'horas', 'hora_inicio']);
        $lines = PackingLine::query()->where('activo', true)->orderBy('especie')->orderBy('nombre')->get(['id', 'nombre', 'tipo', 'especie', 'especies']);

        $defaultEspecies = $request->query('especies');
        if (! is_array($defaultEspecies)) {
            $defaultEspecies = $request->filled('especie') ? [(string) $request->query('especie')] : [];
        }

        $defaultEspecies = collect($defaultEspecies)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        return Inertia::render('Planning/Processes/Create', [
            'especies' => $especies,
            'shifts' => $shifts,
            'lines' => $lines,
            'defaults' => [
                'especie' => $defaultEspecies[0] ?? $request->query('especie'),
                'especies' => $defaultEspecies,
                'planning_mode' => $request->query('planning_mode', 'normal'),
                'fecha' => $request->query('fecha'),
                'shift_id' => $request->query('shift_id'),
            ],
        ]);
    }

    public function store(StorePackingProcessRequest $request)
    {
        $this->authorizePlanning($request);

        $speciesList = collect($request->input('especies', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values();

        if ($speciesList->isEmpty()) {
            throw ValidationException::withMessages([
                'especies' => 'Debes seleccionar al menos una especie.',
            ]);
        }

        $requestedLineIds = collect($request->input('included_packing_line_ids') ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $planningMode = in_array((string) $request->input('planning_mode', 'normal'), ['normal', 'reembalaje'], true)
            ? (string) $request->input('planning_mode', 'normal')
            : 'normal';

        $created = DB::transaction(function () use ($request, $speciesList, $requestedLineIds, $planningMode) {
            return $speciesList->map(function (string $especie) use ($request, $requestedLineIds, $planningMode) {
                $speciesLineIds = PackingLine::query()
                    ->where('activo', true)
                    ->forEspecie($especie)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values();

                if ($speciesLineIds->isEmpty()) {
                    throw ValidationException::withMessages([
                        'especies' => "La especie {$especie} no tiene líneas/cámaras activas configuradas.",
                    ]);
                }

                $includedForSpecies = $requestedLineIds->isNotEmpty()
                    ? $requestedLineIds->intersect($speciesLineIds)->values()
                    : $speciesLineIds;

                if ($includedForSpecies->isEmpty()) {
                    throw ValidationException::withMessages([
                        'included_packing_line_ids' => "No hay líneas/cámaras seleccionadas para la especie {$especie}.",
                    ]);
                }

                $payload = [
                    'especie' => $especie,
                    'exportadora' => null, // se completa desde Recepción al confirmar (1 lote = 1 proceso)
                    'fecha' => $request->date('fecha'),
                    'shift_id' => $request->integer('shift_id'),
                    'extra_horas' => 0,
                    'estado' => PlanningProcessStatus::BORRADOR,
                    'creado_por' => $request->user()?->id,
                    'included_packing_line_ids' => $includedForSpecies->all(),
                    'pedidos' => $request->filled('pedidos') ? (string) $request->input('pedidos') : null,
                ];

                if (Schema::hasColumn('processes', 'planning_mode')) {
                    $payload['planning_mode'] = $planningMode;
                }

                return PackingProcess::create($payload);
            })->values();
        });

        if ($created->count() === 1) {
            return redirect()
                ->route('planning.processes.show', $created->first())
                ->with('success', 'Proceso creado. Ahora puedes generar la propuesta.');
        }

        return redirect()
            ->route('planning.processes.index')
            ->with('success', 'Se crearon '.$created->count().' procesos (uno por especie seleccionada).');
    }

    public function show(Request $request, PackingProcess $process)
    {
        $this->authorizePlanning($request);

        $process->load(['shift', 'lots.packingLine', 'lots.lastPackagingChange.user', 'lineOverrides']);
        $this->enrichLotsWithSqlsrvSdp($process);

        // Exportadora (snapshot) para mostrar en UI: si aún no está seteada, la inferimos desde Recepción.
        // No guardamos aquí para evitar side-effects; se persiste al confirmar.
        if (! is_string($process->exportadora) || trim((string) $process->exportadora) === '') {
            try {
                $ngs = $process->lots
                    ->pluck('n_g_recepcion')
                    ->filter()
                    ->map(fn ($n) => trim((string) $n))
                    ->filter(fn ($n) => $n !== '')
                    ->unique()
                    ->values()
                    ->all();
                if (! empty($ngs)) {
                    $vals = Recepcion::query()
                        ->whereIn('numero_g_recepcion', $ngs)
                        ->pluck('exportadora')
                        ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                        ->map(fn ($v) => trim((string) $v))
                        ->unique()
                        ->values();
                    if ($vals->count() === 1) {
                        $process->setAttribute('exportadora', $vals->first());
                    } elseif ($vals->count() > 1) {
                        $process->setAttribute('exportadora', 'VARIAS');
                    }
                }
            } catch (\Throwable) {
                // noop
            }
        }

        $extraByLine = $process->lineOverrides
            ->mapWithKeys(fn ($r) => [(int) $r->packing_line_id => (float) $r->extra_horas])
            ->all();

        $includedLineIds = collect($process->included_packing_line_ids ?: [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();
        $allLinesQuery = PackingLine::query()
            ->where('activo', true)
            ->forEspecie($process->especie);

        $allLines = $allLinesQuery->orderBy('nombre')->get();

        $linesQuery = (clone $allLinesQuery);
        if ($includedLineIds->isNotEmpty()) {
            $linesQuery->whereIn('id', $includedLineIds->all());
        }
        $lines = $linesQuery->orderBy('nombre')->get();

        $toLineMeta = function (PackingLine $line) use ($process, $extraByLine) {
            $binsPorHora = $this->capacityResolver->resolveBinsPorHora(
                $line->id,
                $process->especie,
                $process->shift_id,
                Carbon::parse($process->fecha)
            );

            $binsPorHora = $binsPorHora ? (float) $binsPorHora : null;
            $extraHoras = (float) ($extraByLine[(int) $line->id] ?? 0);
            $shiftHoras = (float) ($process->shift?->horas ?? 0) + $extraHoras;
            $capacidadTurno = $binsPorHora ? (int) round($binsPorHora * $shiftHoras) : null;

            return [
                'id' => $line->id,
                'nombre' => $line->nombre,
                'tipo' => $line->tipo?->value ?? (string) $line->tipo,
                'especie' => $line->especie,
                'especies' => $line->especies ?? null,
                'bins_por_hora' => $binsPorHora,
                'extra_horas' => $extraHoras,
                'capacidad_bins_turno' => $capacidadTurno,
            ];
        };

        $lineMeta = $lines->map($toLineMeta)->values();
        $allLineMeta = $allLines->map($toLineMeta)->values();
        $isRepackMode = Str::lower(trim((string) ($process->planning_mode ?? 'normal'))) === 'reembalaje';

        $defaultRepackEspecie = trim((string) ($process->especie ?? ''));
        $repackEspecieFilter = $request->has('especie')
            ? trim((string) $request->query('especie', ''))
            : $defaultRepackEspecie;

        // Inventario (panel izquierdo) con filtros.
        $invFilters = [
            'q' => (string) $request->query('q', ''),
            'especie' => $repackEspecieFilter,
            'variedad' => (string) $request->query('variedad', ''),
            'productor' => (string) $request->query('productor', ''),
            'embalaje' => (string) $request->query('embalaje', ''),
            'nota_calidad' => (string) $request->query('nota_calidad', ''),
            'brix_min' => $request->query('brix_min'),
            'brix_max' => $request->query('brix_max'),
        ];

        $inventoryFilterOptions = [
            'especies' => [],
            'variedades' => [],
            'variedadesByEspecie' => [],
            'productores' => [],
            'embalajes' => [],
        ];

        if ($isRepackMode) {
            $inventoryFilterOptions = $this->buildRepackInventoryFilterOptions($invFilters);
            $inventory = $this->inventoryRepository->getRepackAvailableLots([
                'especie' => $invFilters['especie'] ?: null,
                'q' => $invFilters['q'] ?: null,
                'variedad' => $invFilters['variedad'] ?: null,
                'productor' => $invFilters['productor'] ?: null,
                'embalaje' => $invFilters['embalaje'] ?: null,
                'limit' => 300,
            ])->map(function (array $row) {
                return [
                    ...$row,
                    'setup_nota_calidad' => $row['nota_calidad_sqlsrv'] ?? null,
                    'setup_calibre' => null,
                    'setup_color' => null,
                    'brix' => null,
                    'quality_warning' => false,
                    'exportable_percentage' => null,
                    'defectos_calidad' => [],
                    'defectos_condicion' => [],
                ];
            })->values();
        } else {
            $inventory = $this->inventoryRepository->getAvailableLots([
                'especie' => $process->especie,
                'q' => $invFilters['q'] ?: null,
                'variedad' => $invFilters['variedad'] ?: null,
                'limit' => 200,
            ])->values();

            $inventoryNg = $inventory
                ->pluck('n_g_recepcion')
                ->map(fn ($v) => trim((string) $v))
                ->filter(fn ($v) => $v !== '')
                ->unique()
                ->values()
                ->all();

            $qualityDetailsByNg = [];
            if (! empty($inventoryNg)) {
                $recepciones = Recepcion::query()
                    ->select(['id', 'numero_g_recepcion'])
                    ->whereIn('numero_g_recepcion', $inventoryNg)
                    ->with([
                        'calidad:id,recepcion_id',
                        'calidad.detalles:id,calidad_id,tipo_item,detalle_item,porcentaje_muestra',
                    ])
                    ->get();

                foreach ($recepciones as $recepcion) {
                    $ng = trim((string) ($recepcion->numero_g_recepcion ?? ''));
                    if ($ng === '') {
                        continue;
                    }

                    $detalles = $recepcion->calidad?->detalles ?? collect();

                    $qualityDetailsByNg[$ng] = [
                        'exportable_percentage' => $this->calculateExportablePercentageFromDetalles($detalles),
                        'defectos_calidad' => $this->extractDefectRowsByTipo($detalles, 'DEFECTOS DE CALIDAD'),
                        'defectos_condicion' => $this->extractDefectRowsByTipo($detalles, 'DEFECTOS DE CONDICION'),
                    ];
                }
            }

            $qualityMap = $this->qualityRepository->getQualityByNGRecepcion($inventory->pluck('n_g_recepcion')->all());
            $inventory = $inventory->map(function (array $row) use ($qualityMap, $qualityDetailsByNg) {
                $n = trim((string) ($row['n_g_recepcion'] ?? ''));
                $q = $qualityMap[$n] ?? null;
                $qualityExtra = $qualityDetailsByNg[$n] ?? null;
                return [
                    ...$row,
                    'setup_nota_calidad' => $q['setup_nota_calidad'] ?? null,
                    'setup_calibre' => $q['setup_calibre'] ?? ($row['calibre'] ?? null),
                    'setup_color' => $q['setup_color'] ?? null,
                    'brix' => $q['brix'] ?? null,
                    'quality_warning' => (bool) ($q['warning'] ?? false),
                    'exportable_percentage' => $qualityExtra['exportable_percentage'] ?? null,
                    'defectos_calidad' => $qualityExtra['defectos_calidad'] ?? [],
                    'defectos_condicion' => $qualityExtra['defectos_condicion'] ?? [],
                ];
            });

            if ($invFilters['nota_calidad'] !== '') {
                $inventory = $inventory->filter(fn ($row) => (string) ($row['setup_nota_calidad'] ?? '') === $invFilters['nota_calidad'])->values();
            }
            if ($invFilters['brix_min'] !== null && $invFilters['brix_min'] !== '') {
                $min = (float) $invFilters['brix_min'];
                $inventory = $inventory->filter(fn ($row) => ($row['brix'] ?? null) !== null && (float) $row['brix'] >= $min)->values();
            }
            if ($invFilters['brix_max'] !== null && $invFilters['brix_max'] !== '') {
                $max = (float) $invFilters['brix_max'];
                $inventory = $inventory->filter(fn ($row) => ($row['brix'] ?? null) !== null && (float) $row['brix'] <= $max)->values();
            }
        }

        $inventory = $this->enrichInventoryRowsWithSqlsrvSdp($inventory, (string) ($process->especie ?? ''));

        // Badges (México / Mosca) por productor+variedad para esta fecha.
        $badges = [
            'inventory' => [],
            'lots' => [],
        ];

        $date = $process->fecha ? Carbon::parse($process->fecha)->toDateString() : now()->toDateString();
        $season = EstimationSeason::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first()
            ?: EstimationSeason::query()->orderByDesc('is_active')->orderByDesc('id')->first();

        $seasonId = (int) ($season?->id ?? 0);

        $byCsg = User::role('Productor')->get(['id', 'csg'])->mapWithKeys(function (User $u) {
            $key = mb_strtolower(trim((string) ($u->csg ?? '')));
            return $key !== '' ? [$key => (int) $u->id] : [];
        });

        $varNames = collect()
            ->merge($inventory->pluck('variedad')->filter())
            ->merge($process->lots->pluck('n_variedad')->filter())
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values();

        $variedadIdByName = $varNames->isNotEmpty()
            ? Variedad::query()->whereIn('name', $varNames->all())->pluck('id', 'name')
            : collect();

        $producerIds = collect()
            ->merge($inventory->pluck('csg_productor')->filter())
            ->merge($process->lots->pluck('csg_productor')->filter())
            ->map(fn ($csg) => mb_strtolower(trim((string) $csg)))
            ->filter()
            ->unique()
            ->map(fn ($csg) => $byCsg->get($csg))
            ->filter()
            ->unique()
            ->values();

        $variedadIds = $variedadIdByName->values()->map(fn ($id) => (int) $id)->unique()->values();

        if ($seasonId > 0 && $producerIds->isNotEmpty() && $variedadIds->isNotEmpty()) {
            $mexPairs = DB::table('estimation_biweekly_rows as r')
                ->join('estimation_biweekly_versions as v', 'v.id', '=', 'r.estimation_biweekly_version_id')
                ->where('v.season_id', $seasonId)
                ->where('v.status', 'active')
                ->whereDate('r.dia', $date)
                ->where('r.mexico', 1)
                ->whereIn('r.producer_id', $producerIds->all())
                ->whereIn('r.variedad_id', $variedadIds->all())
                ->select(['r.producer_id', 'r.variedad_id'])
                ->distinct()
                ->get();

            $mexSet = collect($mexPairs)->mapWithKeys(fn ($p) => [((int) $p->producer_id).':'.((int) $p->variedad_id) => true]);

            $moscaPairs = DB::table('estimation_rows as r')
                ->join('estimation_versions as v', 'v.id', '=', 'r.estimation_version_id')
                ->where('v.season_id', $seasonId)
                ->where('v.status', 'active')
                ->where('v.type', '!=', 'bisemanal')
                ->where('r.radio_mosca', 1)
                ->whereIn('r.producer_id', $producerIds->all())
                ->whereIn('r.variedad_id', $variedadIds->all())
                ->select(['r.producer_id', 'r.variedad_id'])
                ->distinct()
                ->get();

            $moscaSet = collect($moscaPairs)->mapWithKeys(fn ($p) => [((int) $p->producer_id).':'.((int) $p->variedad_id) => true]);

            foreach ($inventory as $row) {
                $ng = (string) ($row['n_g_recepcion'] ?? '');
                $csg = mb_strtolower(trim((string) ($row['csg_productor'] ?? '')));
                $pid = $csg !== '' ? $byCsg->get($csg) : null;
                $vName = (string) ($row['variedad'] ?? '');
                $vid = $vName !== '' ? $variedadIdByName->get($vName) : null;
                $key = $pid && $vid ? ((int) $pid).':'.((int) $vid) : null;

                $badges['inventory'][$ng] = [
                    'mexico' => $key ? (bool) ($mexSet[$key] ?? false) : false,
                    'mosca' => $key ? (bool) ($moscaSet[$key] ?? false) : false,
                ];
            }

            foreach ($process->lots as $lot) {
                $csg = mb_strtolower(trim((string) ($lot->csg_productor ?? '')));
                $pid = $csg !== '' ? $byCsg->get($csg) : null;
                $vName = (string) ($lot->n_variedad ?? '');
                $vid = $vName !== '' ? $variedadIdByName->get($vName) : null;
                $key = $pid && $vid ? ((int) $pid).':'.((int) $vid) : null;

                $badges['lots'][(string) $lot->id] = [
                    'mexico' => $key ? (bool) ($mexSet[$key] ?? false) : false,
                    'mosca' => $key ? (bool) ($moscaSet[$key] ?? false) : false,
                ];
            }
        }

        // Destinos disponibles según matriz (para filtrar sugerencias de embalaje).
        $especieKey = Str::upper(Str::ascii((string) ($process->especie ?? '')));
        $matrix = (Str::contains($especieKey, 'CHERR') || Str::contains($especieKey, 'CEREZ')) ? 'cherries' : 'carozos';

        $packagingDestinosAvailable = PackagingMatrixRule::query()
            ->where('matrix', $matrix)
            ->whereNotNull('destino')
            ->where('destino', '!=', '')
            ->distinct()
            ->orderBy('destino')
            ->pluck('destino')
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values()
            ->all();

        return Inertia::render('Planning/Processes/Show', [
            'process' => $process,
            'planningMode' => $isRepackMode ? 'reembalaje' : 'normal',
            'lines' => $lineMeta,
            'allLines' => $allLineMeta,
            'inventory' => $inventory->values(),
            'inventoryFilters' => $invFilters,
            'inventoryFilterOptions' => $inventoryFilterOptions,
            'packagingDestinosAvailable' => $packagingDestinosAvailable,
            'allowSplit' => (bool) config('planning.allow_split', false),
            'badges' => $badges,
        ]);
    }

    private function buildRepackInventoryFilterOptions(array $invFilters): array
    {
        $fromSqlsrv = $this->inventoryRepository->getRepackFilterOptions([
            'especie' => trim((string) ($invFilters['especie'] ?? '')) ?: null,
            'variedad' => trim((string) ($invFilters['variedad'] ?? '')) ?: null,
            'q' => trim((string) ($invFilters['q'] ?? '')) ?: null,
        ]);

        $especies = Especie::query()
            ->with(['variedads' => fn ($q) => $q->select(['id', 'name', 'especie_id'])->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);

        $especieOptions = [];
        $variedadesByEspecie = [];
        $allVariedades = [];

        foreach ($especies as $especie) {
            $name = trim((string) ($especie->name ?? ''));
            if ($name === '') {
                continue;
            }

            $especieOptions[] = [
                'value' => $name,
                'label' => $name,
                'searchValue' => $name,
            ];

            $key = Str::lower($name);
            $varOptions = collect($especie->variedads ?? [])
                ->map(function ($variedad) {
                    $varName = trim((string) ($variedad->name ?? ''));
                    if ($varName === '') {
                        return null;
                    }

                    return [
                        'value' => $varName,
                        'label' => $varName,
                        'searchValue' => $varName,
                    ];
                })
                ->filter()
                ->unique(fn ($o) => Str::lower((string) ($o['value'] ?? '')))
                ->sortBy(fn ($o) => Str::lower((string) ($o['label'] ?? '')))
                ->values()
                ->all();

            $variedadesByEspecie[$key] = $varOptions;
            foreach ($varOptions as $opt) {
                $allVariedades[] = $opt;
            }
        }

        $variedades = collect($allVariedades)
            ->unique(fn ($o) => Str::lower((string) ($o['value'] ?? '')))
            ->sortBy(fn ($o) => Str::lower((string) ($o['label'] ?? '')))
            ->values()
            ->all();

        $productores = collect($fromSqlsrv['productores'] ?? [])
            ->map(function ($p) {
                $value = trim((string) $p);
                if ($value === '') {
                    return null;
                }

                return [
                    'value' => $value,
                    'label' => $value,
                    'searchValue' => $value,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'especies' => $especieOptions,
            'variedades' => $variedades,
            'variedadesByEspecie' => $variedadesByEspecie,
            'productores' => $productores,
            'embalajes' => array_values($fromSqlsrv['embalajes'] ?? []),
        ];
    }

    private function normalizeDetailType(?string $value): string
    {
        return Str::upper(Str::ascii(trim((string) $value)));
    }

    private function calculateExportablePercentageFromDetalles($detalles): float
    {
        $rows = collect($detalles);

        $sumByType = function (callable $predicate) use ($rows): float {
            return (float) $rows
                ->filter(function ($d) use ($predicate) {
                    $tipo = $this->normalizeDetailType((string) ($d->tipo_item ?? ''));
                    return $predicate($tipo);
                })
                ->sum(fn ($d) => (float) ($d->porcentaje_muestra ?? 0));
        };

        $defectosCalidad = $sumByType(fn (string $t) => $t === 'DEFECTOS DE CALIDAD');
        $defectosCondicion = $sumByType(fn (string $t) => $t === 'DEFECTOS DE CONDICION');
        $danosPlaga = $sumByType(fn (string $t) => str_contains($t, 'PLAGA'));

        $defectosCalidadPrecalibre = (float) $rows
            ->filter(fn ($d) => $this->normalizeDetailType((string) ($d->tipo_item ?? '')) === 'DEFECTOS DE CALIDAD')
            ->filter(fn ($d) => $this->normalizeDetailType((string) ($d->detalle_item ?? '')) === 'PRECALIBRE')
            ->sum(fn ($d) => (float) ($d->porcentaje_muestra ?? 0));

        $defectosCalidadAjustado = $defectosCalidad - $defectosCalidadPrecalibre;
        $totalDefectos = $defectosCalidadAjustado + $defectosCondicion + $danosPlaga + $defectosCalidadPrecalibre;

        return max(0, round(100 - $totalDefectos, 2));
    }

    private function extractDefectRowsByTipo($detalles, string $tipoObjetivo): array
    {
        $target = $this->normalizeDetailType($tipoObjetivo);
        $acc = [];

        foreach (collect($detalles) as $d) {
            $tipo = $this->normalizeDetailType((string) ($d->tipo_item ?? ''));
            if ($tipo !== $target) {
                continue;
            }

            $detalleItem = trim((string) ($d->detalle_item ?? ''));
            if ($detalleItem === '') {
                $detalleItem = 'SIN DETALLE';
            }

            $value = (float) ($d->porcentaje_muestra ?? 0);
            if (! isset($acc[$detalleItem])) {
                $acc[$detalleItem] = 0.0;
            }
            $acc[$detalleItem] += $value;
        }

        return collect($acc)
            ->map(fn ($value, $name) => [
                'detalle_item' => (string) $name,
                'porcentaje_muestra' => round((float) $value, 2),
            ])
            ->sortByDesc('porcentaje_muestra')
            ->values()
            ->all();
    }

    public function generate(Request $request, PackingProcess $process)
    {
        $this->authorizePlanning($request);

        if (in_array($process->estado?->value ?? (string) $process->estado, [PlanningProcessStatus::CONFIRMADO->value, PlanningProcessStatus::CERRADO->value], true)) {
            return back()->with('error', 'Este proceso ya está confirmado/cerrado y no se puede regenerar.');
        }

        $this->generator->generate($process);

        return back()->with('success', 'Propuesta generada. Ajusta el orden y confirma.');
    }

    public function destroy(Request $request, PackingProcess $process)
    {
        $this->authorizePlanning($request);

        $processId = (int) $process->id;
        $process->delete();

        return redirect()
            ->route('planning.processes.index', [
                'especie' => trim((string) $request->input('especie', '')) ?: null,
            ])
            ->with('success', "Proceso #{$processId} eliminado correctamente.");
    }

    public function updateLineOrder(Request $request)
    {
        $this->authorizePlanning($request);

        if (! Schema::hasTable('process_line_orders')) {
            return back()->with('error', 'No se puede guardar el orden porque falta aplicar migraciones.');
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['required', 'integer', 'min:1'],
            'line_id' => ['required', 'integer', 'min:1'],
            'process_ids' => ['required', 'array', 'min:1'],
            'process_ids.*' => ['integer', 'min:1'],
        ]);

        $date = (string) $validated['date'];
        $shiftId = (int) $validated['shift_id'];
        $lineId = (int) $validated['line_id'];
        $requestedIds = collect($validated['process_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($requestedIds->isEmpty()) {
            return back()->with('error', 'Debes enviar al menos un proceso para guardar el orden.');
        }

        $validProcessIds = PackingProcess::query()
            ->whereDate('fecha', $date)
            ->where('shift_id', $shiftId)
            ->whereHas('lots', fn ($q) => $q->where('packing_line_id', $lineId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($validProcessIds->isEmpty()) {
            return back()->with('error', 'No hay procesos válidos para ese día/turno/línea.');
        }

        $validById = $validProcessIds->flip();
        $requestedValid = $requestedIds
            ->filter(fn ($id) => $validById->has((int) $id))
            ->values();

        if ($requestedValid->isEmpty()) {
            return back()->with('error', 'El orden enviado no coincide con los procesos de la línea.');
        }

        $existingOrderByProcess = DB::table('process_line_orders')
            ->where('fecha', $date)
            ->where('shift_id', $shiftId)
            ->where('packing_line_id', $lineId)
            ->whereIn('process_id', $validProcessIds->all())
            ->orderBy('orden')
            ->get(['process_id', 'orden'])
            ->mapWithKeys(fn ($row) => [(int) $row->process_id => (int) $row->orden]);

        $remaining = $validProcessIds
            ->filter(fn ($id) => ! $requestedValid->contains((int) $id))
            ->sort(function (int $a, int $b) use ($existingOrderByProcess) {
                $orderA = (int) ($existingOrderByProcess[$a] ?? PHP_INT_MAX);
                $orderB = (int) ($existingOrderByProcess[$b] ?? PHP_INT_MAX);
                if ($orderA !== $orderB) {
                    return $orderA <=> $orderB;
                }
                return $a <=> $b;
            })
            ->values();

        $finalOrder = $requestedValid->concat($remaining)->values();
        $userId = (int) ($request->user()?->id ?? 0);
        $now = now();

        DB::transaction(function () use ($date, $shiftId, $lineId, $finalOrder, $userId, $now) {
            DB::table('process_line_orders')
                ->where('fecha', $date)
                ->where('shift_id', $shiftId)
                ->where('packing_line_id', $lineId)
                ->whereNotIn('process_id', $finalOrder->all())
                ->delete();

            $rows = $finalOrder->values()->map(function (int $processId, int $idx) use ($date, $shiftId, $lineId, $userId, $now) {
                return [
                    'fecha' => $date,
                    'shift_id' => $shiftId,
                    'packing_line_id' => $lineId,
                    'process_id' => $processId,
                    'orden' => $idx + 1,
                    'updated_by' => $userId > 0 ? $userId : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            DB::table('process_line_orders')->upsert(
                $rows,
                ['fecha', 'shift_id', 'packing_line_id', 'process_id'],
                ['orden', 'updated_by', 'updated_at']
            );
        });

        return back()->with('success', 'Orden de procesos guardado.');
    }

    public function updateLots(UpdatePackingProcessLotsRequest $request, PackingProcess $process)
    {
        $this->authorizePlanning($request);

        $status = (string) ($process->estado?->value ?? (string) $process->estado);
        if ($status === PlanningProcessStatus::CERRADO->value) {
            return back()->with('error', 'Este proceso está cerrado. No se puede editar.');
        }

        $process->loadMissing('lots');

        // En CONFIRMADO permitimos edición completa:
        // - Exigimos motivo (para versionar instructivo).
        $isConfirmed = $status === PlanningProcessStatus::CONFIRMADO->value;
        $changeReason = trim((string) $request->input('change_reason', ''));

        $hasIntent =
            $request->has('included_packing_line_ids')
            || $request->has('line_extra_hours')
            || $request->has('pedidos')
            || $request->filled('add_n_g_recepcion')
            || $request->filled('add_source_key')
            || $request->filled('split_id')
            || (! empty($request->input('remove_ids')))
            || (! empty($request->input('lots')));

        if ($isConfirmed && $hasIntent && $changeReason === '') {
            return back()->with('error', 'Debes indicar el motivo del cambio para editar un proceso confirmado.');
        }

        $changed = false;
        DB::transaction(function () use ($request, $process, &$changed) {
            if ($request->has('pedidos')) {
                $newPedidos = trim((string) $request->input('pedidos', ''));
                $newPedidos = $newPedidos !== '' ? $newPedidos : null;
                if (($process->pedidos ?? null) !== $newPedidos) {
                    $process->forceFill(['pedidos' => $newPedidos])->save();
                    $changed = true;
                }
            }

            if ($request->has('included_packing_line_ids')) {
                $newIncluded = collect($request->input('included_packing_line_ids') ?: [])
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $lineIdsWithLots = $process->lots->pluck('packing_line_id')->map(fn ($id) => (int) $id)->unique()->values();
                $removed = $lineIdsWithLots->diff($newIncluded);
                if ($removed->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'included_packing_line_ids' => 'No puedes quitar líneas/cámaras que tienen lotes. Mueve o quita los lotes primero.',
                    ]);
                }

                $process->forceFill([
                    'included_packing_line_ids' => $newIncluded->all(),
                ])->save();
                $changed = true;
            }

            if ($request->has('line_extra_hours')) {
                $rows = collect($request->input('line_extra_hours') ?: [])
                    ->map(function ($r) {
                        return [
                            'packing_line_id' => (int) ($r['packing_line_id'] ?? 0),
                            'extra_horas' => (float) ($r['extra_horas'] ?? 0),
                        ];
                    })
                    ->filter(fn ($r) => ($r['packing_line_id'] ?? 0) > 0)
                    ->unique('packing_line_id')
                    ->values();

                foreach ($rows as $row) {
                    $lineId = (int) $row['packing_line_id'];
                    $extra = (float) $row['extra_horas'];
                    if ($extra <= 0) {
                        $deleted = \App\Models\PackingProcessLineOverride::query()
                            ->where('process_id', $process->id)
                            ->where('packing_line_id', $lineId)
                            ->delete();
                        if ($deleted > 0) {
                            $changed = true;
                        }
                        continue;
                    }

                    \App\Models\PackingProcessLineOverride::query()->updateOrCreate([
                        'process_id' => $process->id,
                        'packing_line_id' => $lineId,
                    ], [
                        'extra_horas' => $extra,
                    ]);
                    $changed = true;
                }
            }

            $removeIds = collect($request->input('remove_ids') ?: [])
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($removeIds->isNotEmpty()) {
                $deleted = PackingProcessLot::query()
                    ->where('process_id', $process->id)
                    ->whereIn('id', $removeIds->all())
                    ->delete();
                if ($deleted > 0) {
                    $changed = true;
                }
            }

            $lots = $request->input('lots') ?: [];
            // Validación previa: si hay cambio de embalaje, exigir motivo.
            $missingReason = [];
            foreach ($lots as $lotData) {
                $lotId = (int) ($lotData['id'] ?? 0);
                if ($lotId <= 0) {
                    continue;
                }

                $lot = PackingProcessLot::query()
                    ->where('process_id', $process->id)
                    ->where('id', $lotId)
                    ->first();
                if (! $lot) {
                    continue;
                }

                $newC = isset($lotData['c_embalaje']) && $lotData['c_embalaje'] !== '' ? (string) $lotData['c_embalaje'] : null;
                $newN = isset($lotData['n_embalaje']) && $lotData['n_embalaje'] !== '' ? (string) $lotData['n_embalaje'] : null;

                $oldC = $lot->c_embalaje ? (string) $lot->c_embalaje : null;
                $oldN = $lot->n_embalaje ? (string) $lot->n_embalaje : null;

                $changed = ($oldC !== $newC) || ($oldN !== $newN);
                if (! $changed) {
                    continue;
                }

                $reason = trim((string) ($lotData['packaging_change_reason'] ?? ''));
                if ($reason === '') {
                    $missingReason[] = (string) $lot->n_g_recepcion;
                }
            }
            if (! empty($missingReason)) {
                $preview = array_slice($missingReason, 0, 8);
                $more = count($missingReason) > 8 ? ' (+'.(count($missingReason) - 8).' más)' : '';
                return back()->with('error', 'Debes indicar el motivo del cambio de embalaje. Lotes: '.implode(', ', $preview).$more.'.');
            }

            foreach ($lots as $lotData) {
                $lot = PackingProcessLot::query()
                    ->where('process_id', $process->id)
                    ->where('id', (int) $lotData['id'])
                    ->first();
                if (! $lot) {
                    continue;
                }

                $before = [
                    'packing_line_id' => (int) $lot->packing_line_id,
                    'orden' => (int) $lot->orden,
                    'destino' => $lot->destino ? (string) $lot->destino : null,
                    'c_embalaje' => $lot->c_embalaje ? (string) $lot->c_embalaje : null,
                    'n_embalaje' => $lot->n_embalaje ? (string) $lot->n_embalaje : null,
                    'cp2_cajas_por_pallet' => $lot->cp2_cajas_por_pallet,
                ];

                $newC = isset($lotData['c_embalaje']) && $lotData['c_embalaje'] !== '' ? (string) $lotData['c_embalaje'] : null;
                $newN = isset($lotData['n_embalaje']) && $lotData['n_embalaje'] !== '' ? (string) $lotData['n_embalaje'] : null;

                $oldC = $lot->c_embalaje ? (string) $lot->c_embalaje : null;
                $oldN = $lot->n_embalaje ? (string) $lot->n_embalaje : null;

                $changed = ($oldC !== $newC) || ($oldN !== $newN);
                if ($changed) {
                    $reason = trim((string) ($lotData['packaging_change_reason'] ?? ''));
                    PackingProcessLotPackagingChange::create([
                        'process_lot_id' => $lot->id,
                        'process_id' => $process->id,
                        'user_id' => auth()->id(),
                        'from_c_embalaje' => $oldC,
                        'from_n_embalaje' => $oldN,
                        'to_c_embalaje' => $newC,
                        'to_n_embalaje' => $newN,
                        'reason' => $reason,
                        'created_at' => now(),
                    ]);
                }

                $lot->forceFill([
                    'packing_line_id' => (int) $lotData['packing_line_id'],
                    'orden' => (int) $lotData['orden'],
                    'destino' => isset($lotData['destino']) && trim((string) $lotData['destino']) !== '' ? trim((string) $lotData['destino']) : null,
                    'c_embalaje' => $newC,
                    'n_embalaje' => $newN,
                    'cp2_cajas_por_pallet' => $lotData['cp2_cajas_por_pallet'] ?? null,
                ])->save();
                // Compatibilidad: si la migración aún no se ha corrido, no intentar escribir columnas nuevas.
                // (Evita: Unknown column 'packaging_indications' / 'extra_packagings')
                if (Schema::hasColumn('process_lots', 'packaging_indications')) {
                    $lot->forceFill([
                        'packaging_indications' => isset($lotData['packaging_indications']) && trim((string) $lotData['packaging_indications']) !== ''
                            ? trim((string) $lotData['packaging_indications'])
                            : null,
                    ])->save();
                }
                if (Schema::hasColumn('process_lots', 'extra_packagings')) {
                    $lot->forceFill([
                        'extra_packagings' => (function () use ($lotData) {
                            $rows = $lotData['extra_packagings'] ?? null;
                            if (! is_array($rows)) {
                                return null;
                            }
                            $out = [];
                            foreach ($rows as $r) {
                                if (! is_array($r)) {
                                    continue;
                                }
                                $c = isset($r['c_embalaje']) ? trim((string) $r['c_embalaje']) : '';
                                $n = isset($r['n_embalaje']) ? trim((string) $r['n_embalaje']) : '';
                                $cp2 = $r['cp2_cajas_por_pallet'] ?? null;
                                $ind = isset($r['indications']) ? trim((string) $r['indications']) : '';

                                // Evitar basura: si no hay código ni texto, no guardar.
                                if ($c === '' && $n === '' && $ind === '') {
                                    continue;
                                }

                                $out[] = [
                                    'c_embalaje' => $c !== '' ? $c : null,
                                    'n_embalaje' => $n !== '' ? $n : null,
                                    'cp2_cajas_por_pallet' => is_numeric($cp2) ? (int) $cp2 : null,
                                    'indications' => $ind !== '' ? $ind : null,
                                ];
                            }
                            return empty($out) ? null : $out;
                        })(),
                    ])->save();
                }

                $after = [
                    'packing_line_id' => (int) $lot->packing_line_id,
                    'orden' => (int) $lot->orden,
                    'destino' => $lot->destino ? (string) $lot->destino : null,
                    'c_embalaje' => $lot->c_embalaje ? (string) $lot->c_embalaje : null,
                    'n_embalaje' => $lot->n_embalaje ? (string) $lot->n_embalaje : null,
                    'cp2_cajas_por_pallet' => $lot->cp2_cajas_por_pallet,
                ];
                if ($before !== $after) {
                    $changed = true;
                }
            }

            if ($request->filled('add_source_key') || $request->filled('add_n_g_recepcion')) {
                $lineId = (int) $request->input('add_packing_line_id');
                $sourceTypeInput = Str::lower(trim((string) $request->input('add_source_type', '')));
                $sourceType = in_array($sourceTypeInput, ['recepcion', 'reembalaje'], true)
                    ? $sourceTypeInput
                    : (Str::lower(trim((string) ($process->planning_mode ?? 'normal'))) === 'reembalaje' ? 'reembalaje' : 'recepcion');

                if ($sourceType === 'reembalaje') {
                    $sourceKey = trim((string) $request->input('add_source_key', ''));
                    if ($sourceKey === '') {
                        return;
                    }

                    $hasSourceType = Schema::hasColumn('process_lots', 'source_type');
                    $hasSourceKey = Schema::hasColumn('process_lots', 'source_key');
                    $alreadyQuery = PackingProcessLot::query()
                        ->where('process_id', $process->id);
                    if ($hasSourceType && $hasSourceKey) {
                        $alreadyQuery
                            ->where('source_type', 'reembalaje')
                            ->where('source_key', $sourceKey);
                    } else {
                        $alreadyQuery->where('n_g_recepcion', $sourceKey);
                    }
                    $already = $alreadyQuery->exists();
                    if ($already) {
                        return;
                    }

                    // Si el proceso está limitado a ciertas líneas, al agregar a una nueva la incluimos automáticamente.
                    $included = collect($process->included_packing_line_ids ?: [])->map(fn ($id) => (int) $id)->values();
                    if ($included->isNotEmpty() && ! $included->contains($lineId)) {
                        $process->forceFill([
                            'included_packing_line_ids' => $included->push($lineId)->unique()->values()->all(),
                        ])->save();
                    }

                    $this->addRepackLotToProcess($process, $sourceKey, $lineId);
                    $changed = true;
                } else {
                    $n = trim((string) $request->input('add_n_g_recepcion', ''));
                    if ($n === '') {
                        return;
                    }

                    $already = PackingProcessLot::query()
                        ->where('process_id', $process->id)
                        ->where('n_g_recepcion', $n)
                        ->exists();
                    if ($already) {
                        return;
                    }

                    // Si el proceso está limitado a ciertas líneas, al agregar a una nueva la incluimos automáticamente.
                    $included = collect($process->included_packing_line_ids ?: [])->map(fn ($id) => (int) $id)->values();
                    if ($included->isNotEmpty() && ! $included->contains($lineId)) {
                        $process->forceFill([
                            'included_packing_line_ids' => $included->push($lineId)->unique()->values()->all(),
                        ])->save();
                    }

                    $this->addInventoryLotToProcess($process, $n, $lineId);
                    $changed = true;
                }
            }

            if ($request->filled('split_id')) {
                $splitId = (int) $request->input('split_id');
                $splitBins = (int) $request->input('split_bins');
                $toLineId = (int) $request->input('split_to_packing_line_id');

                // Si el proceso está limitado a ciertas líneas, al dividir hacia una nueva la incluimos automáticamente.
                $included = collect($process->included_packing_line_ids ?: [])->map(fn ($id) => (int) $id)->values();
                if ($included->isNotEmpty() && ! $included->contains($toLineId)) {
                    $process->forceFill([
                        'included_packing_line_ids' => $included->push($toLineId)->unique()->values()->all(),
                    ])->save();
                }

                $lot = PackingProcessLot::query()
                    ->where('process_id', $process->id)
                    ->where('id', $splitId)
                    ->lockForUpdate()
                    ->first();

                if ($lot && $splitBins > 0 && $splitBins < (int) $lot->cantidad_bins) {
                    $originalBins = (int) $lot->cantidad_bins;
                    $originalPeso = $lot->peso_neto !== null ? (float) $lot->peso_neto : null;

                    $newPeso = null;
                    $remainingPeso = $originalPeso;
                    if ($originalPeso !== null && $originalBins > 0) {
                        $newPeso = round($originalPeso * ($splitBins / $originalBins), 3);
                        $remainingPeso = round($originalPeso - $newPeso, 3);
                    }

                    $maxSplitIndex = (int) PackingProcessLot::query()
                        ->where('process_id', $process->id)
                        ->where('n_g_recepcion', $lot->n_g_recepcion)
                        ->max('split_index');

                    $maxOrdenTarget = (int) PackingProcessLot::query()
                        ->where('process_id', $process->id)
                        ->where('packing_line_id', $toLineId)
                        ->max('orden');

                    // Reduce original
                    $lot->forceFill([
                        'cantidad_bins' => $originalBins - $splitBins,
                        'peso_neto' => $remainingPeso,
                    ])->save();

                    // Create new part (mismo proceso, otra línea/cámara).
                    $newLotPayload = [
                        'process_id' => $process->id,
                        'packing_line_id' => $toLineId,
                        'n_g_recepcion' => $lot->n_g_recepcion,
                        'split_index' => $maxSplitIndex + 1,
                        'setup_nota_calidad' => $lot->setup_nota_calidad,
                        'setup_calibre' => $lot->setup_calibre,
                        'setup_color' => $lot->setup_color,
                        'setup_hash' => $lot->setup_hash,
                        'brix' => $lot->brix,
                        'variedad_id' => $lot->variedad_id,
                        'n_variedad' => $lot->n_variedad,
                        'destino' => $lot->destino,
                        'id_productor' => $lot->id_productor,
                        'c_productor' => $lot->c_productor,
                        'csg_productor' => $lot->csg_productor,
                        'n_productor' => $lot->n_productor,
                        'fecha_recepcion' => $lot->fecha_recepcion,
                        'tipo_proceso' => $lot->tipo_proceso,
                        'variedad_original' => $lot->variedad_original,
                        'productor_real' => $lot->productor_real,
                        'categoria_origen' => $lot->categoria_origen,
                        'pulpa' => $lot->pulpa,
                        'huerto' => $lot->huerto,
                        'sdp_centrocosto' => $lot->sdp_centrocosto,
                        'envase_origen' => $lot->envase_origen,
                        'altura_origen' => $lot->altura_origen,
                        'c_embalaje' => $lot->c_embalaje,
                        'n_embalaje' => $lot->n_embalaje,
                        'cp2_cajas_por_pallet' => $lot->cp2_cajas_por_pallet,
                        'cantidad_bins' => $splitBins,
                        'peso_neto' => $newPeso,
                        'orden' => $maxOrdenTarget + 1,
                        'inicio_estimado' => null,
                        'fin_estimado' => null,
                        'estado' => 'PROPUESTO',
                    ];

                    if (Schema::hasColumn('process_lots', 'source_type')) {
                        $newLotPayload['source_type'] = $lot->source_type;
                    }
                    if (Schema::hasColumn('process_lots', 'source_key')) {
                        $newLotPayload['source_key'] = $lot->source_key;
                    }
                    if (Schema::hasColumn('process_lots', 'source_folio')) {
                        $newLotPayload['source_folio'] = $lot->source_folio;
                    }
                    if (Schema::hasColumn('process_lots', 'source_n_g_proceso')) {
                        $newLotPayload['source_n_g_proceso'] = $lot->source_n_g_proceso;
                    }
                    if (Schema::hasColumn('process_lots', 'source_lote')) {
                        $newLotPayload['source_lote'] = $lot->source_lote;
                    }
                    if (Schema::hasColumn('process_lots', 'source_c_embalaje')) {
                        $newLotPayload['source_c_embalaje'] = $lot->source_c_embalaje;
                    }
                    if (Schema::hasColumn('process_lots', 'source_n_embalaje')) {
                        $newLotPayload['source_n_embalaje'] = $lot->source_n_embalaje;
                    }
                    if (Schema::hasColumn('process_lots', 'source_categoria')) {
                        $newLotPayload['source_categoria'] = $lot->source_categoria;
                    }
                    if (Schema::hasColumn('process_lots', 'source_snapshot')) {
                        $newLotPayload['source_snapshot'] = $lot->source_snapshot;
                    }
                    if (Schema::hasColumn('process_lots', 'packaging_indications')) {
                        $newLotPayload['packaging_indications'] = $lot->packaging_indications;
                    }
                    if (Schema::hasColumn('process_lots', 'extra_packagings')) {
                        $newLotPayload['extra_packagings'] = $lot->extra_packagings;
                    }

                    PackingProcessLot::create($newLotPayload);
                    $changed = true;
                }
            }

            // Si un usuario movió lotes entre líneas, aseguramos que todas las líneas usadas queden incluidas
            // (solo aplica cuando el proceso está limitado a un set de líneas).
            $included = collect($process->included_packing_line_ids ?: [])->map(fn ($id) => (int) $id)->values();
            if ($included->isNotEmpty()) {
                $usedLineIds = PackingProcessLot::query()
                    ->where('process_id', $process->id)
                    ->distinct()
                    ->pluck('packing_line_id')
                    ->map(fn ($id) => (int) $id)
                    ->values();

                $merged = $included->merge($usedLineIds)->unique()->values();
                if ($merged->count() !== $included->count()) {
                    $process->forceFill([
                        'included_packing_line_ids' => $merged->all(),
                    ])->save();
                    $changed = true;
                }
            }

            if ($this->normalizeLotOrderByLine($process)) {
                $changed = true;
            }

        });

        // Recalcular tiempos después de cambios.
        $this->generator->estimateTimes($process);

        // Si el proceso está CONFIRMADO, cada modificación crea una NUEVA versión del instructivo por línea/turno,
        // con motivo y usuario.
        if ($isConfirmed && $changed) {
            $lineIds = PackingProcessLot::query()
                ->where('process_id', $process->id)
                ->distinct()
                ->pluck('packing_line_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $this->storeNewInstructionVersionsForLines($process, $lineIds, $changeReason);
        }

        return back()->with('success', 'Cambios guardados.');
    }

    /**
     * Crea una nueva versión del instructivo por cada línea/cámara (fecha+turno fijos).
     *
     * @param array<int,int> $lineIds
     */
    private function storeNewInstructionVersionsForLines(PackingProcess $process, array $lineIds, string $reason): void
    {
        $date = $process->fecha ? Carbon::parse($process->fecha)->toDateString() : now('America/Santiago')->toDateString();
        $shiftId = (int) ($process->shift_id ?? 0);
        if ($shiftId <= 0 || empty($lineIds)) {
            return;
        }

        $shift = $process->shift ?: Shift::find($shiftId);

        $maxByLine = PlanningInstructionVersion::query()
            ->where('fecha', $date)
            ->where('shift_id', $shiftId)
            ->whereIn('packing_line_id', $lineIds)
            ->selectRaw('packing_line_id, max(version) as max_version')
            ->groupBy('packing_line_id')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->packing_line_id => (int) ($r->max_version ?? 0)])
            ->all();

        $user = auth()->user();
        $changedBy = $user?->id;
        $changedAt = now('America/Santiago');

        foreach ($lineIds as $lineId) {
            $next = (int) (($maxByLine[(int) $lineId] ?? 0) + 1);
            $lineSheets = $this->buildInstructionLineSheets($process, $date, $shiftId, [$lineId]);
            if (empty($lineSheets)) {
                continue;
            }

            $meta = [
                (int) $lineId => [
                    'version' => $next,
                    'changed_by_name' => $user?->name,
                    'changed_at' => $changedAt->toDateTimeString(),
                    'reason' => $reason,
                ],
            ];

            $serializedLineSheets = $this->serializeInstructionLineSheets($lineSheets);
            $html = view('planning.instruction_show_like', [
                'process' => [
                    'id' => (int) $process->id,
                    'fecha' => $date,
                    'especie' => (string) ($process->especie ?? ''),
                    'estado' => $process->estado?->value ?? $process->estado,
                ],
                'shift' => $shift ? [
                    'id' => (int) $shift->id,
                    'codigo' => $shift->codigo,
                    'nombre' => $shift->nombre,
                    'horas' => $shift->horas,
                    'hora_inicio' => $shift->hora_inicio,
                ] : null,
                'lineSheets' => $serializedLineSheets,
                'metaByLineId' => $meta,
            ])->render();

            PlanningInstructionVersion::create([
                'fecha' => $date,
                'shift_id' => $shiftId,
                'packing_line_id' => (int) $lineId,
                'version' => $next,
                'html' => $html,
                'reason' => $reason,
                'changed_by' => $changedBy,
                'changed_at' => $changedAt,
            ]);
        }
    }

    /**
     * Crea versión 1 de instructivo si aún no existe para esas líneas (fecha+turno).
     *
     * @param array<int,int> $lineIds
     */
    private function ensureInitialInstructionVersionsForLines(PackingProcess $process, array $lineIds): void
    {
        $date = $process->fecha ? Carbon::parse($process->fecha)->toDateString() : now('America/Santiago')->toDateString();
        $shiftId = (int) ($process->shift_id ?? 0);
        if ($shiftId <= 0 || empty($lineIds)) {
            return;
        }

        $existing = PlanningInstructionVersion::query()
            ->where('fecha', $date)
            ->where('shift_id', $shiftId)
            ->whereIn('packing_line_id', $lineIds)
            ->select(['packing_line_id'])
            ->distinct()
            ->pluck('packing_line_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missing = collect($lineIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->reject(fn ($id) => in_array($id, $existing, true))
            ->values()
            ->all();

        if (empty($missing)) {
            return;
        }

        $user = auth()->user();
        $changedAt = now('America/Santiago');
        $reason = 'Confirmación inicial';

        foreach ($missing as $lineId) {
            $lineSheets = $this->buildInstructionLineSheets($process, $date, $shiftId, [(int) $lineId]);
            if (empty($lineSheets)) {
                continue;
            }

            $meta = [
                (int) $lineId => [
                    'version' => 1,
                    'changed_by_name' => $user?->name,
                    'changed_at' => $changedAt->toDateTimeString(),
                    'reason' => $reason,
                ],
            ];

            $serializedLineSheets = $this->serializeInstructionLineSheets($lineSheets);
            $html = view('planning.instruction_show_like', [
                'process' => [
                    'id' => (int) $process->id,
                    'fecha' => $date,
                    'especie' => (string) ($process->especie ?? ''),
                    'estado' => $process->estado?->value ?? $process->estado,
                ],
                'shift' => $process->shift ? [
                    'id' => (int) $process->shift->id,
                    'codigo' => $process->shift->codigo,
                    'nombre' => $process->shift->nombre,
                    'horas' => $process->shift->horas,
                    'hora_inicio' => $process->shift->hora_inicio,
                ] : null,
                'lineSheets' => $serializedLineSheets,
                'metaByLineId' => $meta,
            ])->render();

            PlanningInstructionVersion::create([
                'fecha' => $date,
                'shift_id' => $shiftId,
                'packing_line_id' => (int) $lineId,
                'version' => 1,
                'html' => $html,
                'reason' => $reason,
                'changed_by' => $user?->id,
                'changed_at' => $changedAt,
            ]);
        }
    }

    /**
     * Arma el "lineSheets" del instructivo (por turno/línea).
     *
     * @param array<int,int>|null $lineIds
     * @param array<int, array<string, mixed>> $overridesByLineId Mapa: lineId => [key => override]
     * @return array<int, array<string, mixed>>
     */
    private function buildInstructionLineSheets(PackingProcess $process, string $date, int $shiftId, ?array $lineIds = null, array $overridesByLineId = []): array
    {
        $process->loadMissing(['shift', 'lots:process_id,packing_line_id']);

        $statuses = [
            PlanningProcessStatus::CONFIRMADO->value,
            PlanningProcessStatus::EN_PROCESO->value,
            PlanningProcessStatus::CERRADO->value,
        ];

        $processIds = PackingProcess::query()
            ->whereDate('fecha', $date)
            ->where('shift_id', $shiftId)
            ->whereIn('estado', $statuses)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if (! $processIds->contains((int) $process->id)) {
            $processIds->push((int) $process->id);
        }

        $lotsQuery = PackingProcessLot::query()
            ->whereIn('process_id', $processIds->all())
            ->with(['packingLine', 'process']);

        if (! empty($lineIds)) {
            $lotsQuery->whereIn('packing_line_id', $lineIds);
        }

        $lots = $lotsQuery
            ->get()
            ->sortBy([
                ['packing_line_id', 'asc'],
                [fn ($l) => $l->inicio_estimado ? 0 : 1, 'asc'],
                ['inicio_estimado', 'asc'],
                ['process_id', 'asc'],
                ['orden', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($lots->isEmpty()) {
            return [];
        }

        // Exportadora por lote (desde MySQL recepcions): se muestra en la tabla del instructivo.
        // Importante: buildInstructionLineSheets() se usa para vista/edición (Inertia), no solo para PDF.
        $exportadoraByNg = [];
        try {
            $ngs = $lots
                ->pluck('n_g_recepcion')
                ->filter()
                ->map(fn ($n) => trim((string) $n))
                ->filter(fn ($n) => $n !== '')
                ->unique()
                ->values()
                ->all();
            if (! empty($ngs)) {
                $exportadoraByNg = Recepcion::query()
                    ->whereIn('numero_g_recepcion', $ngs)
                    ->pluck('exportadora', 'numero_g_recepcion')
                    ->map(fn ($v) => is_string($v) && trim($v) !== '' ? trim((string) $v) : null)
                    ->all();
            }
        } catch (\Throwable $e) {
            $exportadoraByNg = [];
            Log::debug('No se pudo obtener exportadora desde recepcions (buildInstructionLineSheets): '.$e->getMessage());
        }

        foreach ($lots as $lot) {
            $n = trim((string) ($lot?->n_g_recepcion ?? ''));
            if ($n === '') {
                continue;
            }
            $exp = $exportadoraByNg[$n] ?? null;
            if (! $exp) {
                $exp = $lot?->process?->exportadora ?? null;
            }
            if (is_string($exp) && trim($exp) !== '') {
                $lot->setAttribute('exportadora', trim($exp));
            }
        }

        $grouped = $lots->groupBy(fn ($lot) => $lot->packingLine?->nombre ?? ('Línea '.$lot->packing_line_id));

        $codes = (function () use ($lots): array {
            $base = $lots
                ->pluck('c_embalaje')
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->map(fn ($v) => trim((string) $v))
                ->values()
                ->all();

            $extra = [];
            foreach ($lots as $lot) {
                $rows = $lot?->extra_packagings;
                if (! is_array($rows)) {
                    continue;
                }
                foreach ($rows as $r) {
                    if (! is_array($r)) {
                        continue;
                    }
                    $c = isset($r['c_embalaje']) ? trim((string) $r['c_embalaje']) : '';
                    if ($c !== '') {
                        $extra[] = $c;
                    }
                }
            }

            return collect(array_merge($base, $extra))
                ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                ->map(fn ($v) => trim((string) $v))
                ->unique()
                ->values()
                ->all();
        })();

        $matrixRules = [];
        $matrixRulesByDestCode = [];
        $matrixRulesByCode = [];
        $packCatalogByCode = [];
        if (! empty($codes)) {
            $packCatalogByCode = $this->packagingRepository->getPackagingsByCodes($codes);

            $rows = PackagingMatrixRule::query()
                ->where('activo', true)
                ->whereIn('c_item', $codes)
                ->orderBy('priority')
                ->orderBy('id')
                ->get([
                    'id',
                    'matrix',
                    'especie',
                    'destino',
                    'nota',
                    'c_item',
                    'desc_embalaje',
                    'peso_caja',
                    'allowed_calibres',
                    'calibres_note',
                    'sobre_calibre_note',
                    'priority',
                    'require_sdp',
                ]);

            foreach ($rows as $r) {
                $k = mb_strtolower(trim((string) $r->especie)).'|'.mb_strtoupper(trim((string) $r->destino)).'|'.trim((string) $r->c_item);
                if (! isset($matrixRules[$k])) {
                    $matrixRules[$k] = $r;
                }
                $k2 = mb_strtoupper(trim((string) $r->destino)).'|'.trim((string) $r->c_item);
                if (! isset($matrixRulesByDestCode[$k2])) {
                    $matrixRulesByDestCode[$k2] = $r;
                }
                $k3 = trim((string) $r->c_item);
                if ($k3 !== '' && ! isset($matrixRulesByCode[$k3])) {
                    $matrixRulesByCode[$k3] = $r;
                }
            }
        }

        $tz = 'America/Santiago';
        $startTime = $process->shift?->hora_inicio ? (string) $process->shift->hora_inicio : '08:00:00';
        $shiftStart = Carbon::parse($date.' '.$startTime, $tz);

        // Overrides guardados por versión (Observaciones/Calibres/Pedido) para el instructivo.
        $instructionOverridesByLineId = [];
        try {
            $lineIdsCollection = collect($lineIds ?: [])
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->values();

            $linesForOverrides = $lineIdsCollection->isNotEmpty()
                ? $lineIdsCollection->all()
                : $lots->pluck('packing_line_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();

            foreach ($linesForOverrides as $lid) {
                $lid = (int) $lid;
                if ($lid <= 0) {
                    continue;
                }
                $q = PlanningInstructionVersion::query()
                    ->where('fecha', $date)
                    ->where('shift_id', $shiftId)
                    ->where('packing_line_id', $lid)
                    ->orderByDesc('version');

                $rec = $q->first();
                if ($rec && is_array($rec->overrides)) {
                    $instructionOverridesByLineId[$lid] = $rec->overrides;
                }
            }
        } catch (\Throwable $e) {
            $instructionOverridesByLineId = [];
            Log::debug('No se pudieron cargar overrides del instructivo: '.$e->getMessage());
        }

        // Prioridad de overrides:
        // 1) overrides explícitos recibidos por parámetro (p.ej. al guardar edición actual)
        // 2) fallback a overrides persistidos de la última versión por línea.
        $effectiveOverridesByLineId = [];
        foreach ($instructionOverridesByLineId as $lid => $rows) {
            if (! is_array($rows)) {
                continue;
            }
            $effectiveOverridesByLineId[(int) $lid] = $rows;
        }
        foreach ($overridesByLineId as $lid => $rows) {
            if (! is_array($rows)) {
                continue;
            }
            $lineIdKey = (int) $lid;
            $base = $effectiveOverridesByLineId[$lineIdKey] ?? [];
            $effectiveOverridesByLineId[$lineIdKey] = array_merge($base, $rows);
        }

        $lineSheets = [];
        foreach ($grouped as $lineName => $lineLots) {
            $cursor = $shiftStart->copy();
            $packSummary = [];

            foreach ($lineLots as $lot) {
                $especieLot = (string) ($lot->process?->especie ?? $process->especie ?? '');
                $binsPorHora = $this->capacityResolver->resolveBinsPorHora(
                    (int) $lot->packing_line_id,
                    $especieLot,
                    $shiftId > 0 ? $shiftId : null,
                    Carbon::parse($date)
                );

                $binsPorHora = $binsPorHora ? (float) $binsPorHora : null;
                $minutes = null;
                if ($binsPorHora && $binsPorHora > 0) {
                    $qty = (float) ($lot->cantidad_bins ?? 0);
                    $minutes = (int) max(0, (int) ceil(($qty / $binsPorHora) * 60));
                }

                $start = null;
                $end = null;
                if ($minutes !== null) {
                    $start = $cursor->copy();
                    $end = $cursor->copy()->addMinutes($minutes);
                    $cursor = $end->copy();
                }

                $lot->setAttribute('instruction_inicio', $start);
                $lot->setAttribute('instruction_fin', $end);
                $lot->setAttribute('instruction_bins_por_hora', $binsPorHora);

                $destino = trim((string) ($lot->destino ?? ''));
                $destKey = $destino !== '' ? mb_strtoupper($destino) : '-';

                $appendPackaging = function (?string $code, ?string $name, ?int $cp2, ?string $indications, bool $count) use (
                    &$packSummary,
                    $destKey,
                    $especieLot,
                    $lot,
                    $packCatalogByCode,
                    $matrixRules,
                    $matrixRulesByDestCode,
                    $matrixRulesByCode,
                    $destino,
                    $effectiveOverridesByLineId,
                ): void {
                    $code = trim((string) ($code ?? ''));
                    if ($code === '') {
                        return;
                    }

                    $k = $destKey.'|'.$code.'|'.mb_strtolower(trim($especieLot));
                    $catalog = $packCatalogByCode[$code] ?? null;

                    if (! isset($packSummary[$k])) {
                        $override = null;
                        try {
                            $override = $effectiveOverridesByLineId[(int) $lot->packing_line_id][$k] ?? null;
                        } catch (\Throwable) {
                            $override = null;
                        }

                        $packSummary[$k] = [
                            'key' => $k,
                            'destino' => $destKey,
                            'especie' => $especieLot,
                            'c_item' => $code,
                            'n_item' => is_array($catalog) ? ($catalog['n_item'] ?? null) : null,
                            'etiqueta' => is_array($catalog) ? ($catalog['etiqueta'] ?? null) : null,
                            'cp2' => is_array($catalog) ? ($catalog['cp2_cajas_por_pallet'] ?? null) : null,
                            'altura' => is_array($catalog) ? ($catalog['altura'] ?? null) : null,
                            'cantidad_bins' => 0,
                            'kilos' => 0,
                            'rule' => null,
                            'override' => is_array($override) ? $override : null,
                            'indications' => null,
                        ];

                        if (empty($packSummary[$k]['n_item']) && is_string($name) && trim($name) !== '') {
                            $packSummary[$k]['n_item'] = trim($name);
                        } elseif (empty($packSummary[$k]['n_item']) && ! empty($lot->n_embalaje)) {
                            $packSummary[$k]['n_item'] = (string) $lot->n_embalaje;
                        }

                        if (empty($packSummary[$k]['cp2']) && $cp2) {
                            $packSummary[$k]['cp2'] = (int) $cp2;
                        } elseif (empty($packSummary[$k]['cp2']) && $lot->cp2_cajas_por_pallet) {
                            $packSummary[$k]['cp2'] = (int) $lot->cp2_cajas_por_pallet;
                        }

                        if (empty($packSummary[$k]['altura']) && ! empty($lot->altura_origen)) {
                            $packSummary[$k]['altura'] = (string) $lot->altura_origen;
                        }
                    }

                    if ($count) {
                        $packSummary[$k]['cantidad_bins'] += (int) ($lot->cantidad_bins ?? 0);
                        $packSummary[$k]['kilos'] += (float) ($lot->peso_neto ?? 0);
                    }

                    if (is_string($indications) && trim($indications) !== '') {
                        $txt = trim($indications);
                        if (! empty($packSummary[$k]['indications'])) {
                            $packSummary[$k]['indications'] = trim((string) $packSummary[$k]['indications']).' | '.$txt;
                        } else {
                            $packSummary[$k]['indications'] = $txt;
                        }
                    }

                    $rule = null;
                    if ($destino !== '') {
                        $rk = mb_strtolower(trim($especieLot)).'|'.mb_strtoupper($destino).'|'.$code;
                        if (isset($matrixRules[$rk])) {
                            $rule = $matrixRules[$rk];
                        } else {
                            $rk2 = mb_strtoupper($destino).'|'.$code;
                            if (isset($matrixRulesByDestCode[$rk2])) {
                                $rule = $matrixRulesByDestCode[$rk2];
                            }
                        }
                    }
                    if (! $rule && isset($matrixRulesByCode[$code])) {
                        $rule = $matrixRulesByCode[$code];
                    }
                    if ($rule) {
                        $packSummary[$k]['rule'] = $rule;
                    }
                };

                // Embalaje principal (cuenta bins/kilos).
                $appendPackaging(
                    $lot->c_embalaje ? (string) $lot->c_embalaje : null,
                    $lot->n_embalaje ? (string) $lot->n_embalaje : null,
                    $lot->cp2_cajas_por_pallet ? (int) $lot->cp2_cajas_por_pallet : null,
                    $lot->packaging_indications ? (string) $lot->packaging_indications : null,
                    true,
                );

                // Embalajes extra (solo informativos: no suman bins/kilos).
                $extras = $lot->extra_packagings;
                if (is_array($extras)) {
                    foreach ($extras as $ex) {
                        if (! is_array($ex)) {
                            continue;
                        }
                        $appendPackaging(
                            isset($ex['c_embalaje']) ? (string) $ex['c_embalaje'] : null,
                            isset($ex['n_embalaje']) ? (string) $ex['n_embalaje'] : null,
                            isset($ex['cp2_cajas_por_pallet']) && is_numeric($ex['cp2_cajas_por_pallet']) ? (int) $ex['cp2_cajas_por_pallet'] : null,
                            isset($ex['indications']) ? (string) $ex['indications'] : null,
                            false,
                        );
                    }
                }
            }

            $resolveSpecies = static function ($lot) use ($process): array {
                $label = trim((string) ($lot->process?->especie ?? $process->especie ?? ''));
                if ($label === '') {
                    $label = 'VARIAS';
                }

                $key = Str::upper(Str::ascii($label));
                if ($key === '') {
                    $key = 'VARIAS';
                }

                return [$key, $label];
            };

            $speciesByKey = [];
            foreach ($lineLots as $lot) {
                [$speciesKey, $speciesLabel] = $resolveSpecies($lot);
                if (! isset($speciesByKey[$speciesKey])) {
                    $speciesByKey[$speciesKey] = $speciesLabel;
                }
            }
            if (empty($speciesByKey)) {
                $speciesByKey['VARIAS'] = 'VARIAS';
            }

            $lineId = (int) ($lineLots->first()?->packing_line_id ?? 0);

            foreach ($speciesByKey as $speciesKey => $speciesLabel) {
                $speciesLots = $lineLots
                    ->filter(function ($lot) use ($resolveSpecies, $speciesKey) {
                        [$key] = $resolveSpecies($lot);
                        return $key === $speciesKey;
                    })
                    ->values();

                if ($speciesLots->isEmpty()) {
                    continue;
                }

                $speciesPackSummary = array_values(array_filter($packSummary, function ($row) use ($speciesKey) {
                    if (! is_array($row)) {
                        return false;
                    }
                    $rowSpecies = trim((string) ($row['especie'] ?? ''));
                    $rowSpeciesKey = Str::upper(Str::ascii($rowSpecies));
                    if ($rowSpeciesKey === '') {
                        $rowSpeciesKey = 'VARIAS';
                    }
                    return $rowSpeciesKey === $speciesKey;
                }));

                $sheetKey = $lineId.'|'.Str::lower($speciesKey);

                $lineSheets[] = [
                    'lineId' => $lineId,
                    'lineName' => $lineName,
                    'sheetKey' => $sheetKey,
                    'speciesKey' => Str::lower($speciesKey),
                    'speciesLabel' => $speciesLabel,
                    'kilos' => (float) $speciesLots->sum(fn ($l) => (float) ($l->peso_neto ?? 0)),
                    'exportadoraLabel' => (function () use ($speciesLots) {
                        $vals = $speciesLots
                            ->map(fn ($l) => $l->exportadora ?? ($l->process?->exportadora ?? null))
                            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                            ->map(fn ($v) => trim((string) $v))
                            ->unique()
                            ->values();
                        if ($vals->isEmpty()) {
                            return null;
                        }
                        return $vals->count() === 1 ? $vals->first() : 'VARIAS';
                    })(),
                    'pedidosLabel' => (function () use ($speciesLots) {
                        $vals = $speciesLots
                            ->map(fn ($l) => $l->process?->pedidos)
                            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                            ->map(fn ($v) => trim((string) $v))
                            ->unique()
                            ->values()
                            ->all();
                        if (empty($vals)) {
                            return null;
                        }
                        // No saturar el instructivo: 1 línea (resumen) y limpio.
                        $text = implode(' | ', $vals);
                        return mb_strlen($text) > 180 ? (mb_substr($text, 0, 177).'...') : $text;
                    })(),
                    'lots' => $speciesLots,
                    // Respetar el orden operativo definido en Planning/Processes/Show:
                    // primer uso por secuencia de lotes (orden) + embalajes extra en el orden configurado.
                    'packagingSummary' => $speciesPackSummary,
                ];
            }
        }

        return $lineSheets;
    }

    public function instructionEdit(Request $request, PackingProcess $process)
    {
        $this->authorizePlanning($request);

        $process->load(['shift']);

        $lineId = (int) $request->query('line_id', 0);
        if ($lineId <= 0) {
            abort(404);
        }

        $date = $process->fecha ? Carbon::parse($process->fecha)->toDateString() : now()->toDateString();
        $shiftId = (int) ($process->shift_id ?? 0);

        $latest = PlanningInstructionVersion::query()
            ->where('fecha', $date)
            ->where('shift_id', $shiftId)
            ->where('packing_line_id', $lineId)
            ->orderByDesc('version')
            ->with('changer')
            ->first();

        $overridesByLineId = [];
        if ($latest && is_array($latest->overrides)) {
            $overridesByLineId[$lineId] = $latest->overrides;
        }

        $lineSheets = $this->buildInstructionLineSheets($process, $date, $shiftId, [$lineId], $overridesByLineId);
        $speciesParam = trim((string) $request->query('species', ''));

        $sheet = null;
        if ($speciesParam !== '') {
            $speciesTarget = Str::upper(Str::ascii($speciesParam));
            $sheet = collect($lineSheets)->first(function ($s) use ($speciesTarget) {
                if (! is_array($s)) {
                    return false;
                }
                $label = trim((string) ($s['speciesLabel'] ?? ''));
                $key = Str::upper(Str::ascii($label));
                return $key !== '' && $key === $speciesTarget;
            });
        }

        if (! is_array($sheet)) {
            $sheet = $lineSheets[0] ?? null;
        }
        if (! is_array($sheet)) {
            abort(404);
        }

        $serializedSheet = $this->serializeInstructionLineSheets([$sheet])[0] ?? null;
        $varietiesBySpecies = $this->getInstructionVarietiesBySpeciesFromSheet(
            is_array($serializedSheet) ? $serializedSheet : null,
            (string) ($process->especie ?? '')
        );

        $downloadParams = [
            'process' => $process->id,
            'format' => 'pdf',
            'download' => 1,
            'line_id' => $lineId,
            'version' => $latest?->version,
        ];
        $sheetSpeciesLabel = trim((string) ($sheet['speciesLabel'] ?? ''));
        if ($sheetSpeciesLabel !== '' && Str::upper(Str::ascii($sheetSpeciesLabel)) !== 'VARIAS') {
            $downloadParams['species'] = $sheetSpeciesLabel;
        }
        $downloadUrl = route('planning.processes.instruction', $downloadParams);

        return Inertia::render('Planning/Instructions/Edit', [
            'process' => [
                'id' => (int) $process->id,
                'fecha' => $date,
                'especie' => $process->especie,
                'estado' => $process->estado?->value ?? $process->estado,
            ],
            'shift' => $process->shift ? [
                'id' => (int) $process->shift->id,
                'codigo' => $process->shift->codigo,
                'nombre' => $process->shift->nombre,
                'horas' => $process->shift->horas,
                'hora_inicio' => $process->shift->hora_inicio,
            ] : null,
            'lineId' => $lineId,
            'processTypeOptions' => $this->getInstructionProcessTypeOptions(),
            'categoryOptions' => $this->getInstructionCategoryOptions(),
            'latestVersion' => $latest ? [
                'version' => (int) $latest->version,
                'changed_at' => $latest->changed_at?->tz('America/Santiago')->toDateTimeString(),
                'changed_by_name' => $latest->changer?->name,
                'reason' => $latest->reason,
            ] : null,
            'sheet' => $serializedSheet,
            'varietiesBySpecies' => $varietiesBySpecies,
            'downloadUrl' => $downloadUrl,
        ]);
    }

    public function instructionUpdate(Request $request, PackingProcess $process)
    {
        $this->authorizePlanning($request);

        $process->load(['shift']);

        $data = $request->validate([
            'line_id' => ['required', 'integer', 'min:1'],
            'change_reason' => ['required', 'string', 'min:3', 'max:500'],
            'species' => ['nullable', 'string', 'max:80'],
            'lots' => ['nullable', 'array'],
            'lots.*.id' => ['required_with:lots', 'integer', 'min:1'],
            // Puede venir snapshot histórico (ej: "Recepcion Fruta Gran"), luego lo normalizamos.
            'lots.*.tipo_proceso' => ['nullable', 'string', 'max:120'],
            'lots.*.categoria_origen' => ['nullable', 'string', 'max:120'],
            'lots.*.n_variedad' => ['nullable', 'string', 'max:120'],
            'lots.*.pulpa' => ['nullable', 'string', 'max:120'],
            'lots.*.huerto' => ['nullable', 'string', 'in:Tipo A,Tipo B,Tipo C,Tipo C*'],
            'rows' => ['nullable', 'array'],
            'rows.*.key' => ['required_with:rows', 'string'],
            // Campos editables del bloque "Destino + Embalajes"
            'rows.*.destino' => ['nullable', 'string', 'max:30'],
            'rows.*.c_item' => ['nullable', 'string', 'max:60'],
            'rows.*.desc_embalaje' => ['nullable', 'string', 'max:200'],
            'rows.*.etiqueta' => ['nullable', 'string', 'max:80'],
            'rows.*.peso_caja' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.cp2' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'rows.*.altura' => ['nullable', 'string', 'max:80'],
            'rows.*.calibres' => ['nullable', 'string', 'max:500'],
            'rows.*.indications' => ['nullable', 'string', 'max:2000'],
            'rows.*.observaciones' => ['nullable', 'string', 'max:2000'],
            'rows.*.count' => ['nullable', 'string', 'max:120'],
            'rows.*.pedido' => ['nullable', 'string', 'max:500'],
        ]);

        $lineId = (int) $data['line_id'];
        $reason = trim((string) $data['change_reason']);
        $speciesFilter = trim((string) ($data['species'] ?? ''));

        $date = $process->fecha ? Carbon::parse($process->fecha)->toDateString() : now()->toDateString();
        $shiftId = (int) ($process->shift_id ?? 0);

        $baseVersion = (int) PlanningInstructionVersion::query()
            ->where('fecha', $date)
            ->where('shift_id', $shiftId)
            ->where('packing_line_id', $lineId)
            ->max('version');
        $nextVersion = max(1, $baseVersion + 1);

        $overrides = [];
        foreach (($data['rows'] ?? []) as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $destino = trim((string) ($row['destino'] ?? ''));
            $cItem = trim((string) ($row['c_item'] ?? ''));
            $descEmb = trim((string) ($row['desc_embalaje'] ?? ''));
            $etiq = trim((string) ($row['etiqueta'] ?? ''));
            $altura = trim((string) ($row['altura'] ?? ''));
            $indications = trim((string) ($row['indications'] ?? ''));
            $count = trim((string) ($row['count'] ?? ''));

            $pesoCaja = $row['peso_caja'] ?? null;
            $pesoCaja = is_numeric($pesoCaja) ? (float) $pesoCaja : null;

            $cp2 = $row['cp2'] ?? null;
            $cp2 = is_numeric($cp2) ? (int) $cp2 : null;

            $ov = [
                'destino' => $destino !== '' ? $destino : null,
                'c_item' => $cItem !== '' ? $cItem : null,
                'desc_embalaje' => $descEmb !== '' ? $descEmb : null,
                'etiqueta' => $etiq !== '' ? $etiq : null,
                'peso_caja' => $pesoCaja,
                'cp2' => $cp2,
                'altura' => $altura !== '' ? $altura : null,
                'calibres' => ($v = trim((string) ($row['calibres'] ?? ''))) !== '' ? $v : null,
                'indications' => $indications !== '' ? $indications : null,
                'observaciones' => ($v = trim((string) ($row['observaciones'] ?? ''))) !== '' ? $v : null,
                'count' => $count !== '' ? $count : null,
                'pedido' => ($v = trim((string) ($row['pedido'] ?? ''))) !== '' ? $v : null,
            ];
            $overrides[$key] = $ov;
        }

        // Si la UI no envía todas las filas por algún motivo, conservamos overrides previos
        // y sobreescribimos solo las claves que llegan en este guardado.
        try {
            $latestOverrides = PlanningInstructionVersion::query()
                ->where('fecha', $date)
                ->where('shift_id', $shiftId)
                ->where('packing_line_id', $lineId)
                ->orderByDesc('version')
                ->value('overrides');

            if (is_array($latestOverrides)) {
                $overrides = array_merge($latestOverrides, $overrides);
            }
        } catch (\Throwable $e) {
            Log::debug('No se pudieron combinar overrides previos del instructivo: '.$e->getMessage());
        }

        $user = $request->user();
        $changedAt = now('America/Santiago');

        // Persistir cambios de "Procesos / lotes" (tipo/categoría/pulpa) en process_lots.
        $incomingLots = collect($data['lots'] ?? [])
            ->filter(fn ($row) => is_array($row) && (int) ($row['id'] ?? 0) > 0)
            ->keyBy(fn ($row) => (int) ($row['id'] ?? 0));

        $updatedLotsCount = 0;
        if ($incomingLots->isNotEmpty()) {
            // Guardar por ID de lote (clave real), evitando filtros adicionales que puedan bloquear.
            $lotsToUpdate = PackingProcessLot::query()
                ->whereIn('id', $incomingLots->keys()->all())
                ->get();

            $variedadNames = $lotsToUpdate
                ->map(function ($lot) use ($incomingLots) {
                    $row = (array) ($incomingLots->get((int) $lot->id) ?? []);
                    $incoming = trim((string) ($row['n_variedad'] ?? ''));
                    if ($incoming !== '') {
                        return $incoming;
                    }
                    return trim((string) ($lot->n_variedad ?: $lot->variedad_original));
                })
                ->filter(fn ($v) => $v !== '')
                ->unique()
                ->values()
                ->all();
            $pulpaByVariedad = $this->getPulpaByVariedadNames($variedadNames);

            foreach ($lotsToUpdate as $lot) {
                $row = (array) ($incomingLots->get((int) $lot->id) ?? []);
                $tipoRaw = trim((string) ($row['tipo_proceso'] ?? ''));
                $categoria = trim((string) ($row['categoria_origen'] ?? ''));
                $nVariedad = trim((string) ($row['n_variedad'] ?? ''));
                $pulpa = trim((string) ($row['pulpa'] ?? ''));
                $huerto = trim((string) ($row['huerto'] ?? ''));
                $destino = trim((string) ($lot->destino ?? ''));

                // Normalización de tipo de proceso para persistencia consistente.
                $tipoNorm = Str::upper(Str::ascii($tipoRaw));
                $tipo = str_contains($tipoNorm, 'REEMB') ? 'Reembalaje' : 'Normal';
                if ($categoria === '') {
                    $categoria = 'Cat 1';
                }
                if ($pulpa === '') {
                    $varKey = $nVariedad !== ''
                        ? $nVariedad
                        : trim((string) ($lot->n_variedad ?: $lot->variedad_original));
                    $pulpa = $varKey !== '' ? (string) ($pulpaByVariedad[$varKey] ?? '') : '';
                }

                $payload = [
                    'tipo_proceso' => $tipo,
                    'categoria_origen' => $categoria,
                    'n_variedad' => $nVariedad !== '' ? $nVariedad : null,
                ];
                if (Schema::hasColumn('process_lots', 'pulpa')) {
                    $payload['pulpa'] = $pulpa !== '' ? $pulpa : null;
                }
                if (Schema::hasColumn('process_lots', 'huerto')) {
                    $isMexico = Str::upper(Str::ascii($destino)) === 'MEXICO';
                    $payload['huerto'] = $isMexico && in_array($huerto, ['Tipo A', 'Tipo B', 'Tipo C', 'Tipo C*'], true)
                        ? $huerto
                        : null;
                }

                $lot->forceFill($payload)->save();
                $updatedLotsCount++;
            }
        }

        $lineSheets = $this->buildInstructionLineSheets($process, $date, $shiftId, [$lineId], [$lineId => $overrides]);

        $metaByLineId = [
            $lineId => [
                'version' => $nextVersion,
                'changed_by_name' => $user?->name,
                'changed_at' => $changedAt->toDateTimeString(),
                'reason' => $reason,
            ],
        ];

        $serializedLineSheets = $this->serializeInstructionLineSheets($lineSheets);
        $html = view('planning.instruction_show_like', [
            'process' => [
                'id' => (int) $process->id,
                'fecha' => $date,
                'especie' => (string) ($process->especie ?? ''),
                'estado' => $process->estado?->value ?? $process->estado,
            ],
            'shift' => $process->shift ? [
                'id' => (int) $process->shift->id,
                'codigo' => $process->shift->codigo,
                'nombre' => $process->shift->nombre,
                'horas' => $process->shift->horas,
                'hora_inicio' => $process->shift->hora_inicio,
            ] : null,
            'lineSheets' => $serializedLineSheets,
            'metaByLineId' => $metaByLineId,
        ])->render();

        PlanningInstructionVersion::create([
            'fecha' => $date,
            'shift_id' => $shiftId,
            'packing_line_id' => $lineId,
            'version' => $nextVersion,
            'html' => $html,
            'overrides' => $overrides,
            'reason' => $reason,
            'changed_by' => $user?->id,
            'changed_at' => $changedAt,
        ]);

        $redirectParams = ['process' => $process->id, 'line_id' => $lineId];
        if ($speciesFilter !== '' && Str::upper(Str::ascii($speciesFilter)) !== 'VARIAS') {
            $redirectParams['species'] = $speciesFilter;
        }

        return redirect()
            ->route('planning.processes.instruction.edit', $redirectParams)
            ->with('success', 'Instructivo guardado correctamente. Versión '.$nextVersion.'. Lotes actualizados: '.$updatedLotsCount.'.');
    }

    private function addInventoryLotToProcess(PackingProcess $process, string $n, int $lineId): void
    {
        $row = $this->inventoryRepository
            ->getAvailableLots(['especie' => $process->especie, 'q' => $n, 'limit' => 50])
            ->first(fn ($r) => (string) ($r['n_g_recepcion'] ?? '') === $n);

        if (! $row) {
            throw ValidationException::withMessages([
                'add_n_g_recepcion' => 'El lote no está disponible (o cambió) en inventario. Refresca y vuelve a intentar.',
            ]);
        }

        $q = $this->qualityRepository->getQualityByNGRecepcion([$n]);
        $snap = $q[$n] ?? ['setup_nota_calidad' => null, 'setup_calibre' => null, 'setup_color' => null, 'brix' => null];

        $maxOrden = (int) PackingProcessLot::query()
            ->where('process_id', $process->id)
            ->where('packing_line_id', $lineId)
            ->max('orden');

        $pack = $this->carozosPackagingMatrix->suggest($row, $snap);
        $variedadName = trim((string) ($row['variedad'] ?? ($row['n_variedad'] ?? '')));
        $pulpaByVariedad = $this->getPulpaByVariedadNames($variedadName !== '' ? [$variedadName] : []);
        $pulpa = $variedadName !== '' ? ($pulpaByVariedad[$variedadName] ?? null) : null;

        $newLotData = [
            'process_id' => $process->id,
            'packing_line_id' => $lineId,
            'n_g_recepcion' => $n,
            'split_index' => 1,
            'setup_nota_calidad' => $snap['setup_nota_calidad'] ?? null,
            'setup_calibre' => $snap['setup_calibre'] ?? ($row['calibre'] ?? null),
            'setup_color' => $snap['setup_color'] ?? null,
            'setup_hash' => sha1(implode('|', [
                (string) ($snap['setup_nota_calidad'] ?? ''),
                (string) ($snap['setup_calibre'] ?? ($row['calibre'] ?? '')),
                (string) ($row['variedad'] ?? ''),
                (string) ($snap['setup_color'] ?? ''),
            ])),
            'brix' => $snap['brix'] ?? null,
            'variedad_id' => null,
            'n_variedad' => $row['variedad'] ?? null,
            'id_productor' => isset($row['id_productor']) ? (int) $row['id_productor'] : null,
            'c_productor' => $row['c_productor'] ?? null,
            'csg_productor' => $row['csg_productor'] ?? null,
            'n_productor' => $row['n_productor'] ?? ($row['productor'] ?? null),
            // Snapshots para instructivo (formato xlsx)
            'fecha_recepcion' => $row['fecha_recepcion'] ?? null,
            'tipo_proceso' => $row['descripcion_tipo'] ?? 'Normal',
            'variedad_original' => $row['n_variedad_original'] ?? null,
            'productor_real' => $row['n_productor_original'] ?? ($row['n_productor'] ?? ($row['productor'] ?? null)),
            'categoria_origen' => $row['categoria'] ?? ($row['n_categoria'] ?? 'Cat 1'),
            'sdp_centrocosto' => $row['sdp_centrocosto'] ?? null,
            'envase_origen' => $row['n_embalaje'] ?? null,
            'altura_origen' => $row['n_altura'] ?? null,
            // Embalaje sugerido por matriz (si hay match); siempre editable en UI.
            'c_embalaje' => $pack['c_item'] ?? null,
            'n_embalaje' => $pack['n_item'] ?? null,
            'cp2_cajas_por_pallet' => $pack['cp2_cajas_por_pallet'] ?? null,
            'cantidad_bins' => (int) ($row['cantidad_bins'] ?? 0),
            'peso_neto' => $row['peso_neto'] ?? null,
            'orden' => $maxOrden + 1,
            'inicio_estimado' => null,
            'fin_estimado' => null,
            'estado' => 'PROPUESTO',
        ];

        if (Schema::hasColumn('process_lots', 'source_type')) {
            $newLotData['source_type'] = 'recepcion';
        }
        if (Schema::hasColumn('process_lots', 'source_key')) {
            $newLotData['source_key'] = $n;
        }
        if (Schema::hasColumn('process_lots', 'source_lote')) {
            $newLotData['source_lote'] = $n;
        }

        if (Schema::hasColumn('process_lots', 'pulpa')) {
            $newLotData['pulpa'] = $pulpa;
        }
        if (Schema::hasColumn('process_lots', 'huerto')) {
            $newLotData['huerto'] = null;
        }
        if (Schema::hasColumn('process_lots', 'source_snapshot')) {
            $newLotData['source_snapshot'] = [
                'n_g_recepcion' => $n,
                'n_variedad' => $row['variedad'] ?? null,
                'n_productor' => $row['n_productor'] ?? ($row['productor'] ?? null),
                'c_embalaje' => $row['c_embalaje'] ?? null,
                'n_embalaje' => $row['n_embalaje'] ?? null,
            ];
        }

        PackingProcessLot::create($newLotData);
    }

    private function addRepackLotToProcess(PackingProcess $process, string $sourceKey, int $lineId): void
    {
        $key = trim($sourceKey);
        $row = $this->inventoryRepository
            ->getRepackAvailableLots([
                'especie' => $process->especie,
                'source_keys' => [$key],
                'limit' => 20,
            ])
            ->first(fn ($r) => trim((string) ($r['source_key'] ?? '')) === $key);

        if (! $row) {
            throw ValidationException::withMessages([
                'add_source_key' => 'El folio/lote de reembalaje no está disponible en inventario. Refresca y vuelve a intentar.',
            ]);
        }

        $nRecepcion = trim((string) ($row['n_g_recepcion'] ?? ''));
        if ($nRecepcion === '') {
            $nRecepcion = trim((string) ($row['loter_unitec'] ?? ''));
        }
        if ($nRecepcion === '') {
            $nRecepcion = $key;
        }

        $q = $this->qualityRepository->getQualityByNGRecepcion([$nRecepcion]);
        $snap = $q[$nRecepcion] ?? ['setup_nota_calidad' => null, 'setup_calibre' => null, 'setup_color' => null, 'brix' => null];

        $maxOrden = (int) PackingProcessLot::query()
            ->where('process_id', $process->id)
            ->where('packing_line_id', $lineId)
            ->max('orden');

        $maxSplitIndex = (int) PackingProcessLot::query()
            ->where('process_id', $process->id)
            ->where('n_g_recepcion', $nRecepcion)
            ->max('split_index');

        $pack = $this->carozosPackagingMatrix->suggest($row, $snap);
        $variedadName = trim((string) ($row['variedad'] ?? ''));
        $pulpaByVariedad = $this->getPulpaByVariedadNames($variedadName !== '' ? [$variedadName] : []);
        $pulpa = $variedadName !== '' ? ($pulpaByVariedad[$variedadName] ?? null) : null;

        $newLotData = [
            'process_id' => $process->id,
            'packing_line_id' => $lineId,
            'n_g_recepcion' => $nRecepcion,
            'split_index' => max(1, $maxSplitIndex + 1),
            'setup_nota_calidad' => $snap['setup_nota_calidad'] ?? ($row['nota_calidad_sqlsrv'] ?? null),
            'setup_calibre' => $snap['setup_calibre'] ?? null,
            'setup_color' => $snap['setup_color'] ?? null,
            'setup_hash' => sha1(implode('|', [
                $key,
                (string) ($variedadName ?? ''),
                (string) ($row['c_embalaje'] ?? ''),
                (string) ($row['n_embalaje'] ?? ''),
            ])),
            'brix' => $snap['brix'] ?? null,
            'variedad_id' => null,
            'n_variedad' => $row['variedad'] ?? null,
            'id_productor' => null,
            'c_productor' => null,
            'csg_productor' => $row['csg_productor'] ?? null,
            'n_productor' => $row['n_productor'] ?? null,
            'fecha_recepcion' => $row['fecha_recepcion'] ?? null,
            'tipo_proceso' => 'Reembalaje',
            'variedad_original' => $row['variedad'] ?? null,
            'productor_real' => $row['n_productor_original'] ?? ($row['n_productor'] ?? null),
            'categoria_origen' => $row['categoria'] ?? 'Cat 1',
            'sdp_centrocosto' => $row['sdp_centrocosto'] ?? null,
            'envase_origen' => $row['n_embalaje'] ?? null,
            'altura_origen' => $row['n_altura'] ?? null,
            'destino' => isset($row['destino']) && trim((string) $row['destino']) !== '' ? trim((string) $row['destino']) : null,
            'c_embalaje' => $pack['c_item'] ?? ($row['c_embalaje'] ?? null),
            'n_embalaje' => $pack['n_item'] ?? ($row['n_embalaje'] ?? null),
            'cp2_cajas_por_pallet' => $pack['cp2_cajas_por_pallet'] ?? null,
            'cantidad_bins' => max(1, (int) ($row['cantidad_bins'] ?? 1)),
            'peso_neto' => $row['peso_neto'] ?? null,
            'orden' => $maxOrden + 1,
            'inicio_estimado' => null,
            'fin_estimado' => null,
            'estado' => 'PROPUESTO',
        ];

        if (Schema::hasColumn('process_lots', 'source_type')) {
            $newLotData['source_type'] = 'reembalaje';
        }
        if (Schema::hasColumn('process_lots', 'source_key')) {
            $newLotData['source_key'] = $key;
        }
        if (Schema::hasColumn('process_lots', 'source_folio')) {
            $newLotData['source_folio'] = $row['folio'] ?? $key;
        }
        if (Schema::hasColumn('process_lots', 'source_n_g_proceso')) {
            $newLotData['source_n_g_proceso'] = $row['n_g_proceso'] ?? null;
        }
        if (Schema::hasColumn('process_lots', 'source_lote')) {
            $newLotData['source_lote'] = ($row['loter_unitec'] ?? null) ?: $nRecepcion;
        }
        if (Schema::hasColumn('process_lots', 'source_c_embalaje')) {
            $newLotData['source_c_embalaje'] = $row['c_embalaje'] ?? null;
        }
        if (Schema::hasColumn('process_lots', 'source_n_embalaje')) {
            $newLotData['source_n_embalaje'] = $row['n_embalaje'] ?? null;
        }
        if (Schema::hasColumn('process_lots', 'source_categoria')) {
            $newLotData['source_categoria'] = $row['categoria'] ?? null;
        }
        if (Schema::hasColumn('process_lots', 'source_snapshot')) {
            $newLotData['source_snapshot'] = [
                'folio' => $row['folio'] ?? $key,
                'n_g_proceso' => $row['n_g_proceso'] ?? null,
                'n_g_recepcion' => $nRecepcion,
                'loter_unitec' => $row['loter_unitec'] ?? null,
                'destino' => $row['destino'] ?? null,
                'c_embalaje' => $row['c_embalaje'] ?? null,
                'n_embalaje' => $row['n_embalaje'] ?? null,
                'categoria' => $row['categoria'] ?? null,
            ];
        }
        if (Schema::hasColumn('process_lots', 'pulpa')) {
            $newLotData['pulpa'] = $pulpa;
        }
        if (Schema::hasColumn('process_lots', 'huerto')) {
            $newLotData['huerto'] = null;
        }

        PackingProcessLot::create($newLotData);
    }

    private function normalizeLotOrderByLine(PackingProcess $process): bool
    {
        $changed = false;

        $lots = PackingProcessLot::query()
            ->where('process_id', $process->id)
            ->orderBy('packing_line_id')
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'packing_line_id', 'orden']);

        $positionByLine = [];

        foreach ($lots as $lot) {
            $lineId = (int) ($lot->packing_line_id ?? 0);
            if ($lineId <= 0) {
                continue;
            }

            $positionByLine[$lineId] = ($positionByLine[$lineId] ?? 0) + 1;
            $expectedOrder = (int) $positionByLine[$lineId];
            if ((int) ($lot->orden ?? 0) === $expectedOrder) {
                continue;
            }

            $lot->forceFill(['orden' => $expectedOrder])->save();
            $changed = true;
        }

        return $changed;
    }

    private function enrichLotsWithSqlsrvSdp(PackingProcess $process): void
    {
        $lookups = [];
        foreach ($process->lots as $lot) {
            $existingSdp = trim((string) ($lot->sdp_centrocosto ?? ''));
            if ($existingSdp !== '') {
                continue;
            }

            $csg = trim((string) ($lot->csg_productor ?? ''));
            $variedad = trim((string) ($lot->n_variedad ?? ''));
            $especie = trim((string) ($process->especie ?? ''));
            if ($csg === '' || $variedad === '' || $especie === '') {
                continue;
            }

            $lookups[] = [
                'csg' => $csg,
                'especie' => $especie,
                'variedad' => $variedad,
            ];
        }

        if (empty($lookups)) {
            return;
        }

        $maps = $this->resolveSqlsrvSdpMaps($lookups);
        foreach ($process->lots as $lot) {
            $existingSdp = trim((string) ($lot->sdp_centrocosto ?? ''));
            if ($existingSdp !== '') {
                continue;
            }

            $tripleKey = $this->buildSdpTripleKey(
                (string) ($lot->csg_productor ?? ''),
                (string) ($process->especie ?? ''),
                (string) ($lot->n_variedad ?? ''),
            );
            $pairKey = $this->buildSdpPairKey(
                (string) ($lot->csg_productor ?? ''),
                (string) ($lot->n_variedad ?? ''),
            );

            $sdp = ($tripleKey !== '' && isset($maps['triple'][$tripleKey]))
                ? $maps['triple'][$tripleKey]
                : (($pairKey !== '' && isset($maps['pair'][$pairKey]))
                    ? $maps['pair'][$pairKey]
                    : null);

            if ($sdp) {
                $lot->setAttribute('sdp_centrocosto', $sdp);
            }
        }
    }

    private function enrichInventoryRowsWithSqlsrvSdp($inventory, string $defaultEspecie)
    {
        if (! $inventory || $inventory->isEmpty()) {
            return $inventory;
        }

        $lookups = [];
        foreach ($inventory as $row) {
            $existingSdp = trim((string) ($row['sdp_centrocosto'] ?? ''));
            if ($existingSdp !== '') {
                continue;
            }

            $csg = trim((string) ($row['csg_productor'] ?? ''));
            $variedad = trim((string) ($row['variedad'] ?? ($row['n_variedad'] ?? '')));
            $especie = trim((string) ($row['especie'] ?? $defaultEspecie));
            if ($csg === '' || $variedad === '' || $especie === '') {
                continue;
            }

            $lookups[] = [
                'csg' => $csg,
                'especie' => $especie,
                'variedad' => $variedad,
            ];
        }

        if (empty($lookups)) {
            return $inventory;
        }

        $maps = $this->resolveSqlsrvSdpMaps($lookups);

        return $inventory->map(function (array $row) use ($maps, $defaultEspecie) {
            $existingSdp = trim((string) ($row['sdp_centrocosto'] ?? ''));
            if ($existingSdp !== '') {
                return $row;
            }

            $tripleKey = $this->buildSdpTripleKey(
                (string) ($row['csg_productor'] ?? ''),
                (string) ($row['especie'] ?? $defaultEspecie),
                (string) ($row['variedad'] ?? ($row['n_variedad'] ?? '')),
            );
            $pairKey = $this->buildSdpPairKey(
                (string) ($row['csg_productor'] ?? ''),
                (string) ($row['variedad'] ?? ($row['n_variedad'] ?? '')),
            );

            $sdp = ($tripleKey !== '' && isset($maps['triple'][$tripleKey]))
                ? $maps['triple'][$tripleKey]
                : (($pairKey !== '' && isset($maps['pair'][$pairKey]))
                    ? $maps['pair'][$pairKey]
                    : null);

            if (! $sdp) {
                return $row;
            }

            return [
                ...$row,
                'sdp_centrocosto' => $sdp,
            ];
        })->values();
    }

    private function resolveSqlsrvSdpMaps(array $lookups): array
    {
        $empty = ['triple' => [], 'pair' => []];
        if (empty($lookups)) {
            return $empty;
        }

        $csgCodes = collect($lookups)
            ->map(fn ($row) => trim((string) ($row['csg'] ?? '')))
            ->filter(fn ($csg) => $csg !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($csgCodes)) {
            return $empty;
        }

        try {
            $entities = DB::connection('sqlsrv')
                ->table('V_ADM_Entidades')
                ->select(['id', 'codigo_sag'])
                ->where('tipo', 'Productor')
                ->whereIn('codigo_sag', $csgCodes)
                ->get();

            $entityIdToCsg = [];
            $entityIds = [];
            foreach ($entities as $entity) {
                $entityId = (int) ($entity->id ?? 0);
                $csg = trim((string) ($entity->codigo_sag ?? ''));
                if ($entityId <= 0 || $csg === '') {
                    continue;
                }
                $entityIdToCsg[$entityId] = $csg;
                $entityIds[] = $entityId;
            }

            if (empty($entityIds)) {
                return $empty;
            }

            $centros = DB::connection('sqlsrv')
                ->table('ADM_P_CentrosCosto')
                ->select(['id_adm_p_entidades', 'nombre', 'sdp'])
                ->whereIn('id_adm_p_entidades', array_values(array_unique($entityIds)))
                ->whereNotNull('sdp')
                ->where('sdp', '!=', '')
                ->get();

            $byTriple = [];
            $byPair = [];
            foreach ($centros as $row) {
                $entityId = (int) ($row->id_adm_p_entidades ?? 0);
                $csg = $entityIdToCsg[$entityId] ?? null;
                if (! $csg) {
                    continue;
                }

                $parsed = $this->parseCentroCostoName((string) ($row->nombre ?? ''));
                if (! $parsed) {
                    continue;
                }

                $sdp = trim((string) ($row->sdp ?? ''));
                if ($sdp === '') {
                    continue;
                }

                [$especie, $variedad] = $parsed;
                $tripleKey = $this->buildSdpTripleKey($csg, $especie, $variedad);
                $pairKey = $this->buildSdpPairKey($csg, $variedad);

                if ($tripleKey !== '' && ! isset($byTriple[$tripleKey])) {
                    $byTriple[$tripleKey] = $sdp;
                }
                if ($pairKey !== '' && ! isset($byPair[$pairKey])) {
                    $byPair[$pairKey] = $sdp;
                }
            }

            return [
                'triple' => $byTriple,
                'pair' => $byPair,
            ];
        } catch (\Throwable $e) {
            Log::warning('No fue posible resolver SDP desde SQL Server', [
                'error' => $e->getMessage(),
            ]);
            return $empty;
        }
    }

    private function parseCentroCostoName(string $name): ?array
    {
        $parts = array_values(array_filter(array_map('trim', explode(' - ', trim($name))), fn ($v) => $v !== ''));
        if (count($parts) < 3) {
            return null;
        }

        $variedad = (string) ($parts[count($parts) - 1] ?? '');
        $especie = (string) ($parts[count($parts) - 2] ?? '');
        if ($variedad === '' || $especie === '') {
            return null;
        }

        return [$especie, $variedad];
    }

    private function normalizeSdpLookupToken(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $ascii = Str::ascii($raw);
        return Str::lower(preg_replace('/\s+/', ' ', $ascii) ?? $ascii);
    }

    private function buildSdpPairKey(?string $csg, ?string $variedad): string
    {
        $c = $this->normalizeSdpLookupToken($csg);
        $v = $this->normalizeSdpLookupToken($variedad);
        if ($c === '' || $v === '') {
            return '';
        }

        return $c.'|'.$v;
    }

    private function buildSdpTripleKey(?string $csg, ?string $especie, ?string $variedad): string
    {
        $c = $this->normalizeSdpLookupToken($csg);
        $e = $this->normalizeSdpLookupToken($especie);
        $v = $this->normalizeSdpLookupToken($variedad);
        if ($c === '' || $e === '' || $v === '') {
            return '';
        }

        return $c.'|'.$e.'|'.$v;
    }

    public function confirm(Request $request, PackingProcess $process)
    {
        $this->authorizePlanning($request);

        if (in_array($process->estado?->value ?? (string) $process->estado, [PlanningProcessStatus::CONFIRMADO->value, PlanningProcessStatus::CERRADO->value], true)) {
            return back()->with('success', 'Este proceso ya está confirmado.');
        }

        $process->loadMissing(['lots:id,process_id,n_g_recepcion,source_type,source_key,destino']);
        $missingDestino = $process->lots
            ->filter(fn ($l) => trim((string) ($l->destino ?? '')) === '')
            ->map(function ($l) {
                $sourceType = Str::lower(trim((string) ($l->source_type ?? '')));
                if ($sourceType === 'reembalaje') {
                    return trim((string) ($l->source_key ?? $l->n_g_recepcion));
                }
                return trim((string) ($l->n_g_recepcion ?? ''));
            })
            ->filter()
            ->values()
            ->all();

        if (! empty($missingDestino)) {
            $preview = array_slice($missingDestino, 0, 8);
            $more = count($missingDestino) > 8 ? ' (+'.(count($missingDestino) - 8).' más)' : '';
            return back()->with('error', 'Antes de confirmar debes seleccionar el destino por lote. Lotes: '.implode(', ', $preview).$more.'.');
        }

        $result = $this->confirmationService->finalizeAndConfirm($process);

        if (($result['mode'] ?? '') === 'split') {
            $created = count($result['created_process_ids'] ?? []);
            $conflicts = count($result['conflicts'] ?? []);
            if (! ($result['ok'] ?? false)) {
                return redirect()
                    ->route('planning.processes.index', ['especie' => null])
                    ->with('error', "Se generaron {$created} procesos, pero hay {$conflicts} conflicto(s). Revisa los procesos en rojo y vuelve a confirmar los que corresponda.");
            }

            // Versionado inicial del instructivo (v1) por línea/turno/fecha.
            $createdIds = collect($result['created_process_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();
            if (! empty($createdIds)) {
                $lineIds = PackingProcessLot::query()
                    ->whereIn('process_id', $createdIds)
                    ->distinct()
                    ->pluck('packing_line_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();
                $this->ensureInitialInstructionVersionsForLines($process, $lineIds);
            }

            return redirect()
                ->route('planning.processes.index', ['especie' => null])
                ->with('success', "Confirmado. Se generaron {$created} procesos (1 por lote).");
        }

        if (! ($result['ok'] ?? false)) {
            return back()->with('error', 'Hay conflictos. Revisa los lotes marcados y vuelve a confirmar.');
        }

        // Versionado inicial del instructivo (v1) por línea/turno/fecha.
        $lineIds = PackingProcessLot::query()
            ->where('process_id', $process->id)
            ->distinct()
            ->pluck('packing_line_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $this->ensureInitialInstructionVersionsForLines($process, $lineIds);

        return back()->with('success', 'Proceso confirmado. Puedes imprimir el instructivo.');
    }

    public function instruction(Request $request, PackingProcess $process)
    {
        $this->authorizePlanning($request);

        $process->load(['shift', 'lots:process_id,packing_line_id']);

        // Instructivo por turno/línea:
        // - Siempre usa la fecha + turno del proceso.
        // - Incluye TODOS los procesos del mismo turno (aunque sean de otra especie) que se ejecutan
        //   en la(s) misma(s) línea(s) del proceso (por defecto), para tener una sola “hoja” operativa
        //   por línea para el turno.
        $date = $process->fecha ? Carbon::parse($process->fecha)->toDateString() : now()->toDateString();
        $shiftId = (int) ($process->shift_id ?? 0);

        $format = (string) $request->query('format', 'html');
        $lineIdParam = $request->filled('line_id') ? (int) $request->query('line_id') : null;
        $versionParam = $request->filled('version') ? (int) $request->query('version') : null;
        $download = $request->boolean('download', false);

        // Nota: no servimos el HTML "congelado" desde planning_instruction_versions.html.
        // Siempre renderizamos el instructivo desde Blade para poder mostrar botones (Editar/Descargar)
        // y aplicar overrides guardados (observaciones/calibres/pedido).

        $lineIds = collect();
        if ($request->filled('line_id')) {
            $lineIds = collect([(int) $request->query('line_id')])->filter(fn ($v) => $v > 0)->values();
        } else {
            $lineIds = $process->lots
                ->pluck('packing_line_id')
                ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values();
        }

        // Overrides opcionales por versión explícita solicitada en la URL.
        $overridesByLineId = [];
        if ($lineIdParam && $lineIdParam > 0 && $versionParam && $versionParam > 0) {
            try {
                $rec = PlanningInstructionVersion::query()
                    ->where('fecha', $date)
                    ->where('shift_id', $shiftId)
                    ->where('packing_line_id', (int) $lineIdParam)
                    ->where('version', (int) $versionParam)
                    ->first();

                if ($rec && is_array($rec->overrides)) {
                    $overridesByLineId[(int) $lineIdParam] = $rec->overrides;
                }
            } catch (\Throwable $e) {
                Log::debug('No se pudieron cargar overrides por versión explícita (instruction): '.$e->getMessage());
            }
        }

        $lineIdsForBuild = $lineIds->isNotEmpty()
            ? $lineIds->all()
            : null;

        $lineSheets = $this->buildInstructionLineSheets(
            $process,
            $date,
            $shiftId,
            $lineIdsForBuild,
            $overridesByLineId
        );

        $speciesParam = trim((string) $request->query('species', ''));
        if ($speciesParam !== '') {
            $speciesTarget = Str::upper(Str::ascii($speciesParam));
            $lineSheets = array_values(array_filter($lineSheets, function ($sheet) use ($speciesTarget) {
                if (! is_array($sheet)) {
                    return false;
                }
                $label = trim((string) ($sheet['speciesLabel'] ?? ''));
                $key = Str::upper(Str::ascii($label));
                return $key !== '' && $key === $speciesTarget;
            }));
        }

        // Meta por línea (para mostrar versión/motivo en el instructivo).
        $metaByLineId = [];
        $sheetLineIds = collect($lineSheets)
            ->map(fn ($s) => (int) ($s['lineId'] ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if (! empty($sheetLineIds)) {
            $maxByLine = PlanningInstructionVersion::query()
                ->where('fecha', $date)
                ->where('shift_id', $shiftId)
                ->whereIn('packing_line_id', $sheetLineIds)
                ->selectRaw('packing_line_id, max(version) as max_version')
                ->groupBy('packing_line_id')
                ->get()
                ->mapWithKeys(fn ($r) => [(int) $r->packing_line_id => (int) ($r->max_version ?? 0)])
                ->all();

            foreach ($sheetLineIds as $lid) {
                $v = (int) ($maxByLine[(int) $lid] ?? 0);
                if ($v <= 0) {
                    continue;
                }
                $latest = PlanningInstructionVersion::query()
                    ->where('fecha', $date)
                    ->where('shift_id', $shiftId)
                    ->where('packing_line_id', $lid)
                    ->where('version', $v)
                    ->with('changer')
                    ->first();
                if (! $latest) {
                    continue;
                }
                $metaByLineId[(int) $lid] = [
                    'version' => (int) $latest->version,
                    'changed_by_name' => $latest->changer?->name,
                    'changed_at' => $latest->changed_at?->tz('America/Santiago')->toDateTimeString(),
                    'reason' => $latest->reason,
                ];
            }
        }

        $serializedLineSheets = $this->serializeInstructionLineSheets($lineSheets);

        // Vista en el sistema (dentro de AuthenticatedLayout): devolvemos Inertia con data estructurada.
        // El PDF/HTML descargable usa el mismo esquema de datos para mantener consistencia visual.
        if (mb_strtolower(trim($format)) === 'html' && ! $download) {
            return Inertia::render('Planning/Instructions/Show', [
                'process' => [
                    'id' => (int) $process->id,
                    'fecha' => $date,
                    'especie' => $process->especie,
                    'estado' => $process->estado?->value ?? $process->estado,
                ],
                'shift' => $process->shift ? [
                    'id' => (int) $process->shift->id,
                    'codigo' => $process->shift->codigo,
                    'nombre' => $process->shift->nombre,
                    'horas' => $process->shift->horas,
                    'hora_inicio' => $process->shift->hora_inicio,
                ] : null,
                'line_id' => $lineIdParam,
                'version' => $versionParam,
                'lineSheets' => $serializedLineSheets,
                'metaByLineId' => $metaByLineId,
            ]);
        }

        // HTML/PDF del instructivo: mismo contenido y columnas que Instructions/Show.jsx.
        $html = view('planning.instruction_show_like', [
            'process' => [
                'id' => (int) $process->id,
                'fecha' => $date,
                'especie' => (string) ($process->especie ?? ''),
                'estado' => $process->estado?->value ?? $process->estado,
            ],
            'shift' => $process->shift ? [
                'id' => (int) $process->shift->id,
                'codigo' => $process->shift->codigo,
                'nombre' => $process->shift->nombre,
                'horas' => $process->shift->horas,
                'hora_inicio' => $process->shift->hora_inicio,
            ] : null,
            'lineSheets' => $serializedLineSheets,
            'metaByLineId' => $metaByLineId,
        ])->render();

        $lineNameForDownload = null;
        if ($lineIdParam && $lineIdParam > 0) {
            $lineNameForDownload = PackingLine::query()->where('id', $lineIdParam)->value('nombre');
        }

        $speciesLabelForFilename = (string) ($process->especie ?? 'VARIAS');
        $speciesParam = trim((string) $request->query('species', ''));
        if ($speciesParam !== '') {
            $speciesLabelForFilename = $speciesParam;
        } elseif ($lineIdParam && $lineIdParam > 0) {
            $lineSpecies = collect($lineSheets)
                ->filter(fn ($s) => is_array($s) && (int) ($s['lineId'] ?? 0) === (int) $lineIdParam)
                ->map(fn ($s) => trim((string) ($s['speciesLabel'] ?? '')))
                ->filter(fn ($v) => $v !== '')
                ->unique()
                ->values();

            if ($lineSpecies->count() === 1) {
                $speciesLabelForFilename = (string) $lineSpecies->first();
            } elseif ($lineSpecies->count() > 1) {
                $speciesLabelForFilename = 'VARIAS';
            }
        }
        $speciesLabelForFilename = trim($speciesLabelForFilename) !== '' ? trim($speciesLabelForFilename) : 'VARIAS';

        $versionForFilename = null;
        if ($lineIdParam && $lineIdParam > 0) {
            if ($versionParam && $versionParam > 0) {
                $versionForFilename = $versionParam;
            } else {
                $rec = PlanningInstructionVersion::query()
                    ->where('fecha', $date)
                    ->where('shift_id', $shiftId)
                    ->where('packing_line_id', $lineIdParam)
                    ->selectRaw('max(version) as v')
                    ->first();
                $v = (int) ($rec?->v ?? 0);
                if ($v > 0) {
                    $versionForFilename = $v;
                }
            }
        }
        return $this->respondInstructionHtmlOrPdf(
            (string) $html,
            $format,
            $date,
            $shiftId,
            $lineIdParam,
            $versionForFilename,
            $lineNameForDownload ? (string) $lineNameForDownload : null,
            $speciesLabelForFilename,
            $download,
        );
    }

    private function respondInstructionHtmlOrPdf(
        string $html,
        string $format,
        string $date,
        int $shiftId,
        ?int $lineId,
        ?int $version,
        ?string $lineName = null,
        ?string $speciesLabel = null,
        bool $download = false,
    )
    {
        $format = mb_strtolower(trim($format ?: 'html'));
        $safeDate = preg_replace('/[^0-9\\-]/', '-', (string) $date) ?: now('America/Santiago')->toDateString();

        $base = "instructivo_{$safeDate}_turno_{$shiftId}";
        if ($lineId && $lineId > 0) {
            $base .= "_linea_{$lineId}";
        }
        if ($version && $version > 0) {
            $base .= "_v{$version}";
        }

        // Nombre solicitado (para descarga): Linea-especie-fecha(ddMMyyyy)_version.pdf
        $downloadFilename = null;
        if ($download) {
            $dmy = Carbon::parse($safeDate, 'America/Santiago')->format('dmY');
            $linePart = trim((string) ($lineName ?: 'Linea'));
            $specPart = trim((string) ($speciesLabel ?: 'VARIAS'));
            $verPart = (int) ($version ?: 1);

            $slug = function (string $v): string {
                $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v) ?: $v;
                $v = preg_replace('/[^A-Za-z0-9\\-\\s_\\.]/', '', $v) ?: $v;
                $v = preg_replace('/\\s+/', '-', trim($v)) ?: $v;
                $v = preg_replace('/\\-+/', '-', $v) ?: $v;
                return $v !== '' ? $v : 'X';
            };

            $downloadFilename = $slug($linePart).'-'.$slug($specPart).'-'.$dmy.'_'.$verPart.'.'.$format;
        }

        if ($format === 'pdf') {
            try {
                $tmpDir = storage_path('app/tmp');
                if (! is_dir($tmpDir)) {
                    @mkdir($tmpDir, 0755, true);
                }

                // Forzar instructivo en una sola hoja (A4 horizontal) usando escala de impresión.
                // Ajustable por entorno: PLANNING_INSTRUCTION_PDF_SCALE (0.1 - 2.0)
                $pdfScale = (float) env('PLANNING_INSTRUCTION_PDF_SCALE', 0.85);
                if ($pdfScale < 0.1 || $pdfScale > 2.0) {
                    $pdfScale = 1;
                }

                $chrome = env(
                    'BROWSERSHOT_CHROME_PATH',
                    env('CHROME_PATH', '/home/forge/.cache/puppeteer/chrome/linux-139.0.7258.138/chrome-linux64/chrome')
                );

                $shot = Browsershot::html($html)
                    ->setTemporaryDirectory($tmpDir)
                    ->setOption('headless', true)
                    ->noSandbox()
                    ->addChromiumArguments([
                        '--no-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-gpu',
                        '--font-render-hinting=none',
                        '--headless=new',
                    ])
                    ->waitUntilNetworkIdle()
                    ->wait(2)
                    ->showBackground()
                    ->format('Letter')
                    ->landscape()
                    ->margins(5, 5, 5, 5)
                    ->setOption('scale', $pdfScale)
                    ->setOption('preferCSSPageSize', false);

                // En producción forzamos binario de Chrome para evitar fallas de Puppeteer cache/path.
                if (! app()->environment('local')) {
                    $shot
                        ->setChromePath($chrome)
                        ->setOption('executablePath', $chrome);
                }

                $pdf = $shot->pdf();

                return response($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.($downloadFilename ?: ($base.'.pdf')).'"',
                    'X-Planning-Instructivo-Version' => $version ? (string) $version : '',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Planning instruction PDF render failed, returning HTML.', [
                    'date' => $date,
                    'shift_id' => $shiftId,
                    'line_id' => $lineId,
                    'version' => $version,
                    'error' => $e->getMessage(),
                ]);

                // Importante: no devolver HTML con nombre ".pdf" porque termina como archivo corrupto
                // (“No se pudo cargar el documento PDF”). Si falla el PDF, entregamos HTML claro.
                $fallbackName = $base.'.html';
                if ($download) {
                    $fallbackName = preg_replace('/\\.pdf$/i', '.html', (string) ($downloadFilename ?: $fallbackName)) ?: $fallbackName;
                }
                $banner = "<div style='padding:10px 12px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:800;margin:10px;border-radius:10px'>No se pudo generar el PDF. Mostrando versión HTML para imprimir.</div>\n";
                return response($banner.$html, 200, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Content-Disposition' => 'inline; filename="'.$fallbackName.'"',
                    'X-Planning-PDF-Failed' => '1',
                ]);
            }
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.($downloadFilename ?: ($base.'.html')).'"',
            'X-Planning-Instructivo-Version' => $version ? (string) $version : '',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $lineSheets
     * @return array<int, array<string, mixed>>
     */
    private function serializeInstructionLineSheets(array $lineSheets): array
    {
        $ngs = collect($lineSheets)
            ->flatMap(function ($sheet) {
                if (! is_array($sheet)) {
                    return [];
                }
                return collect($sheet['lots'] ?? [])->map(function ($lot) {
                    return trim((string) ($lot?->n_g_recepcion ?? ''));
                });
            })
            ->filter(fn ($n) => $n !== '')
            ->unique()
            ->values()
            ->all();

        $exportableByNg = [];
        $defectsByNg = [];
        if (! empty($ngs)) {
            $recepciones = Recepcion::query()
                ->select(['id', 'numero_g_recepcion'])
                ->whereIn('numero_g_recepcion', $ngs)
                ->with([
                    'calidad:id,recepcion_id',
                    'calidad.detalles:id,calidad_id,tipo_item,detalle_item,porcentaje_muestra',
                ])
                ->get();

            foreach ($recepciones as $recepcion) {
                $ng = trim((string) ($recepcion->numero_g_recepcion ?? ''));
                if ($ng === '') {
                    continue;
                }
                $detalles = $recepcion->calidad?->detalles ?? collect();
                $exportableByNg[$ng] = $this->calculateExportablePercentageFromDetalles($detalles);
                $defectsByNg[$ng] = [
                    'defectos_calidad' => $this->extractDefectRowsByTipo($detalles, 'DEFECTOS DE CALIDAD'),
                    'defectos_condicion' => $this->extractDefectRowsByTipo($detalles, 'DEFECTOS DE CONDICION'),
                ];
            }
        }

        $variedadNames = collect($lineSheets)
            ->flatMap(function ($sheet) {
                if (! is_array($sheet)) {
                    return [];
                }
                return collect($sheet['lots'] ?? [])->map(function ($lot) {
                    $nVariedad = trim((string) ($lot?->n_variedad ?? ''));
                    $varOriginal = trim((string) ($lot?->variedad_original ?? ''));
                    return $nVariedad !== '' ? $nVariedad : $varOriginal;
                });
            })
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
        $pulpaByVariedad = $this->getPulpaByVariedadNames($variedadNames);

        $out = [];
        foreach ($lineSheets as $sheet) {
            if (! is_array($sheet)) {
                continue;
            }

            $lots = [];
            foreach (($sheet['lots'] ?? []) as $lot) {
                /** @var PackingProcessLot|mixed $lot */
                $start = $lot?->getAttribute('instruction_inicio') ?: ($lot?->inicio_estimado ?? null);
                $end = $lot?->getAttribute('instruction_fin') ?: ($lot?->fin_estimado ?? null);

                $lots[] = [
                    'id' => (int) ($lot?->id ?? 0),
                    'process_id' => (int) ($lot?->process_id ?? 0),
                    'especie' => (string) ($lot?->process?->especie ?? ''),
                    'n_g_recepcion' => (string) ($lot?->n_g_recepcion ?? ''),
                    'source_type' => (string) ($lot?->source_type ?? 'recepcion'),
                    'source_key' => (string) ($lot?->source_key ?? ''),
                    'source_folio' => (string) ($lot?->source_folio ?? ''),
                    'source_n_g_proceso' => (string) ($lot?->source_n_g_proceso ?? ''),
                    'source_lote' => (string) ($lot?->source_lote ?? ''),
                    'source_c_embalaje' => (string) ($lot?->source_c_embalaje ?? ''),
                    'source_n_embalaje' => (string) ($lot?->source_n_embalaje ?? ''),
                    'source_categoria' => (string) ($lot?->source_categoria ?? ''),
                    'destino' => (string) ($lot?->destino ?? ''),
                    'porcentaje_exportacion' => (function () use ($lot, $exportableByNg) {
                        $ng = trim((string) ($lot?->n_g_recepcion ?? ''));
                        return $ng !== '' && array_key_exists($ng, $exportableByNg)
                            ? (float) $exportableByNg[$ng]
                            : null;
                    })(),
                    'tipo_proceso' => (string) (($lot?->tipo_proceso ?? '') !== '' ? $lot->tipo_proceso : 'Normal'),
                    'variedad_original' => (string) ($lot?->variedad_original ?? ''),
                    'productor_real' => (string) (($lot?->productor_real ?? '') !== '' ? $lot->productor_real : ($lot?->n_productor ?? '')),
                    'csg_productor' => (string) ($lot?->csg_productor ?? ''),
                    'categoria_origen' => (string) (($lot?->categoria_origen ?? '') !== '' ? $lot->categoria_origen : 'Cat 1'),
                    'pulpa' => (function () use ($lot, $pulpaByVariedad) {
                        $current = trim((string) ($lot?->pulpa ?? ''));
                        if ($current !== '') {
                            return $current;
                        }
                        $nVar = trim((string) ($lot?->n_variedad ?? ''));
                        $vOrig = trim((string) ($lot?->variedad_original ?? ''));
                        $key = $nVar !== '' ? $nVar : $vOrig;
                        return $key !== '' ? (string) ($pulpaByVariedad[$key] ?? '') : '';
                    })(),
                    'huerto' => (string) ($lot?->huerto ?? ''),
                    'fecha_recepcion' => $lot?->fecha_recepcion ? (string) $lot->fecha_recepcion : null,
                    'cantidad_bins' => (int) ($lot?->cantidad_bins ?? 0),
                    'peso_neto' => $lot?->peso_neto !== null ? (float) $lot->peso_neto : null,
                    'sdp_centrocosto' => (string) ($lot?->sdp_centrocosto ?? ''),
                    'nota_calidad' => (string) ($lot?->setup_nota_calidad ?? ''),
                    'exportadora' => (string) ($lot?->exportadora ?? ''),
                    'n_variedad' => (string) ($lot?->n_variedad ?? ''),
                    'inicio' => $start ? Carbon::parse($start)->tz('America/Santiago')->toDateTimeString() : null,
                    'fin' => $end ? Carbon::parse($end)->tz('America/Santiago')->toDateTimeString() : null,
                    'defectos_calidad' => (function () use ($lot, $defectsByNg) {
                        $ng = trim((string) ($lot?->n_g_recepcion ?? ''));
                        return $ng !== '' ? (($defectsByNg[$ng]['defectos_calidad'] ?? [])) : [];
                    })(),
                    'defectos_condicion' => (function () use ($lot, $defectsByNg) {
                        $ng = trim((string) ($lot?->n_g_recepcion ?? ''));
                        return $ng !== '' ? (($defectsByNg[$ng]['defectos_condicion'] ?? [])) : [];
                    })(),
                ];
            }

            $packRows = [];
            foreach (($sheet['packagingSummary'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rule = $row['rule'] ?? null;
                $override = is_array($row['override'] ?? null) ? $row['override'] : [];

                $destinoFinal = (string) ($row['destino'] ?? '');
                if (! empty($override['destino'])) {
                    $destinoFinal = (string) $override['destino'];
                }

                $codeFinal = (string) ($row['c_item'] ?? '');
                if (! empty($override['c_item'])) {
                    $codeFinal = (string) $override['c_item'];
                }

                $desc = (string) ($row['n_item'] ?? '');
                if (trim($desc) === '' && $rule?->desc_embalaje) {
                    $desc = (string) $rule->desc_embalaje;
                }
                if (! empty($override['desc_embalaje'])) {
                    $desc = (string) $override['desc_embalaje'];
                }

                $etiquetaFinal = (string) ($row['etiqueta'] ?? '');
                if (! empty($override['etiqueta'])) {
                    $etiquetaFinal = (string) $override['etiqueta'];
                }

                $cp2Final = $row['cp2'] ?? null;
                if (array_key_exists('cp2', $override) && $override['cp2'] !== null && $override['cp2'] !== '') {
                    $cp2Final = is_numeric($override['cp2']) ? (int) $override['cp2'] : $override['cp2'];
                }

                $alturaFinal = (string) ($row['altura'] ?? '');
                if (! empty($override['altura'])) {
                    $alturaFinal = (string) $override['altura'];
                }

                $peso = $rule?->peso_caja ?? null;
                if (array_key_exists('peso_caja', $override) && $override['peso_caja'] !== null && $override['peso_caja'] !== '') {
                    $peso = is_numeric($override['peso_caja']) ? (float) $override['peso_caja'] : $peso;
                }
                $allowed = is_array($rule?->allowed_calibres) ? $rule->allowed_calibres : [];
                $numeric = [];
                foreach ($allowed as $a) {
                    $a = trim((string) $a);
                    if ($a === '') continue;
                    if (is_numeric($a)) $numeric[] = (float) $a;
                }
                $calibresResumen = '-';
                if (! empty($numeric)) {
                    $calibresResumen = ((int) min($numeric)).' AL '.((int) max($numeric));
                } elseif (! empty($allowed)) {
                    $calibresResumen = implode(', ', array_map('strval', $allowed));
                }
                if (! empty($override['calibres'])) {
                    $calibresResumen = (string) $override['calibres'];
                }

                $obs = trim((string) ($rule?->calibres_note ?? ''));
                $sobre = trim((string) ($rule?->sobre_calibre_note ?? ''));
                if ($sobre !== '') {
                    $obs = ($obs !== '' ? ($obs.' · ') : '').$sobre;
                }
                $obsFinal = ! empty($override['observaciones']) ? (string) $override['observaciones'] : ($obs !== '' ? $obs : '-');
                $pedidoFinal = ! empty($override['pedido']) ? (string) $override['pedido'] : '-';

                $bins = (int) ($row['cantidad_bins'] ?? 0);
                $kgs = (float) ($row['kilos'] ?? 0);
                $countTxt = ($bins > 0 || $kgs > 0) ? ('Bins: '.$bins.' · Kg: '.((int) round($kgs))) : '';
                if (! empty($override['count'])) {
                    $countTxt = (string) $override['count'];
                }

                $indicationsFinal = (string) (is_string($row['indications'] ?? null) ? trim((string) $row['indications']) : '');
                if (! empty($override['indications'])) {
                    $indicationsFinal = (string) $override['indications'];
                }

                $packRows[] = [
                    'key' => (string) ($row['key'] ?? ''),
                    'destino' => (string) ($destinoFinal ?? ''),
                    'c_item' => (string) ($codeFinal ?? ''),
                    'desc_embalaje' => (string) ($desc ?: '-'),
                    'etiqueta' => (string) ($etiquetaFinal ?? ''),
                    'peso_caja' => $peso !== null ? (float) $peso : null,
                    'cp2' => $cp2Final ?? null,
                    'altura' => (string) ($alturaFinal ?? ''),
                    'calibres' => (string) ($calibresResumen ?: '-'),
                    'nota' => (string) ($rule?->nota ?? ''),
                    // Observaciones editables (override). Las indicaciones operativas del embalaje van aparte,
                    // para que no se "dupliquen" al guardar una versión del instructivo.
                    'observaciones' => (string) ($obsFinal ?: '-'),
                    'indications' => (string) ($indicationsFinal ?? ''),
                    'count' => $countTxt,
                    'pedido' => (string) ($pedidoFinal ?: '-'),
                ];
            }

            $out[] = [
                'lineId' => (int) ($sheet['lineId'] ?? 0),
                'lineName' => (string) ($sheet['lineName'] ?? ''),
                'sheetKey' => (string) ($sheet['sheetKey'] ?? ''),
                'speciesKey' => (string) ($sheet['speciesKey'] ?? ''),
                'speciesLabel' => (string) ($sheet['speciesLabel'] ?? ''),
                'kilos' => (float) ($sheet['kilos'] ?? 0),
                'exportadoraLabel' => $sheet['exportadoraLabel'] ?? null,
                'pedidosLabel' => $sheet['pedidosLabel'] ?? null,
                'lots' => $lots,
                'packagingSummary' => $packRows,
            ];
        }
        return $out;
    }

    private function getInstructionProcessTypeOptions(): array
    {
        return ['Normal', 'Reembalaje'];
    }

    private function getInstructionCategoryOptions(): array
    {
        try {
            $rows = DB::connection('sqlsrv')->select("
                SELECT codigo, nombre
                FROM FX6_Packing_Garate_Operaciones.dbo.PRO_P_Categorias
                WHERE id_pro_p_categorias_st IN (2,4,6,10)
                ORDER BY codigo
            ");

            $options = collect($rows)
                ->map(function ($row) {
                    $code = trim((string) ($row->codigo ?? ''));
                    $name = trim((string) ($row->nombre ?? ''));
                    if ($name === '') {
                        return null;
                    }
                    if (Str::upper($name) === 'CAT 1') {
                        $name = 'Cat 1';
                    }
                    return [
                        'value' => $name,
                        'label' => $code !== '' ? ($code.' - '.$name) : $name,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $hasCat1 = collect($options)->contains(fn ($o) => Str::upper(trim((string) ($o['value'] ?? ''))) === 'CAT 1');
            if (! $hasCat1) {
                array_unshift($options, ['value' => 'Cat 1', 'label' => 'Cat 1']);
            }

            return $options;
        } catch (\Throwable $e) {
            Log::warning('No se pudieron cargar categorías de instructivo desde SQLSRV', [
                'error' => $e->getMessage(),
            ]);
            return [['value' => 'Cat 1', 'label' => 'Cat 1']];
        }
    }

    /**
     * Opciones de variedad para edición de instructivo, agrupadas por especie.
     *
     * @param array<string, mixed>|null $sheet
     * @return array<string, array<int, array<string, string>>>
     */
    private function getInstructionVarietiesBySpeciesFromSheet(?array $sheet, string $fallbackSpecies): array
    {
        $sheetData = is_array($sheet) ? $sheet : [];

        $normalizeSpecies = static function (?string $value): string {
            $raw = trim((string) $value);
            if ($raw === '') {
                return '';
            }
            $norm = Str::upper(Str::ascii($raw));
            if ($norm === 'VARIAS') {
                return '';
            }
            return Str::lower($raw);
        };

        $speciesKeys = collect([$fallbackSpecies, (string) ($sheetData['speciesLabel'] ?? '')])
            ->merge(collect($sheetData['lots'] ?? [])->map(function ($lot) {
                if (! is_array($lot)) {
                    return '';
                }
                return (string) ($lot['especie'] ?? '');
            }))
            ->map(fn ($v) => $normalizeSpecies((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        $addOption = static function (array &$target, string $value): void {
            $val = trim((string) $value);
            if ($val === '') {
                return;
            }
            $k = Str::lower($val);
            if (! isset($target[$k])) {
                $target[$k] = [
                    'value' => $val,
                    'label' => $val,
                    'searchValue' => $val,
                ];
            }
        };

        $grouped = [];
        try {
            $especies = Especie::query()
                ->with(['variedads' => fn ($q) => $q->select(['id', 'name', 'especie_id'])->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name']);

            foreach ($especies as $especie) {
                $name = trim((string) ($especie->name ?? ''));
                if ($name === '') {
                    continue;
                }
                $speciesKey = $normalizeSpecies($name);
                if ($speciesKey === '') {
                    continue;
                }
                if (! empty($speciesKeys) && ! in_array($speciesKey, $speciesKeys, true)) {
                    continue;
                }

                if (! isset($grouped[$speciesKey])) {
                    $grouped[$speciesKey] = [];
                }

                foreach (($especie->variedads ?? []) as $variedad) {
                    $addOption($grouped[$speciesKey], (string) ($variedad->name ?? ''));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron cargar variedades por especie para instructivo', [
                'error' => $e->getMessage(),
            ]);
        }

        // Garantizar que se muestren variedades ya presentes en el instructivo
        // aunque no estén en el catálogo maestro.
        foreach (($sheetData['lots'] ?? []) as $lot) {
            if (! is_array($lot)) {
                continue;
            }

            $lotSpeciesKey = $normalizeSpecies((string) ($lot['especie'] ?? $fallbackSpecies));
            if ($lotSpeciesKey === '') {
                $lotSpeciesKey = $speciesKeys[0] ?? '';
            }
            if ($lotSpeciesKey === '') {
                continue;
            }

            if (! isset($grouped[$lotSpeciesKey])) {
                $grouped[$lotSpeciesKey] = [];
            }

            $addOption($grouped[$lotSpeciesKey], (string) ($lot['n_variedad'] ?? ''));
            $addOption($grouped[$lotSpeciesKey], (string) ($lot['variedad_original'] ?? ''));
        }

        $union = [];
        foreach ($grouped as $speciesKey => $optionsByKey) {
            $opts = array_values($optionsByKey);
            usort($opts, fn ($a, $b) => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? '')));
            $grouped[$speciesKey] = $opts;

            foreach ($opts as $opt) {
                $k = Str::lower((string) ($opt['value'] ?? ''));
                if ($k === '') {
                    continue;
                }
                if (! isset($union[$k])) {
                    $union[$k] = $opt;
                }
            }
        }

        $all = array_values($union);
        usort($all, fn ($a, $b) => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? '')));
        $grouped['*'] = $all;

        return $grouped;
    }

    /**
     * @param array<int, string> $names
     * @return array<string, string>
     */
    private function getPulpaByVariedadNames(array $names): array
    {
        $list = collect($names)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($list)) {
            return [];
        }

        try {
            $rows = DB::connection('sqlsrv')
                ->table('PRO_P_Variedades')
                ->select(['nombre', 'cp1'])
                ->whereIn('nombre', $list)
                ->get();

            return collect($rows)
                ->mapWithKeys(function ($row) {
                    $name = trim((string) ($row->nombre ?? ''));
                    $cp1 = trim((string) ($row->cp1 ?? ''));
                    if ($name === '') {
                        return [];
                    }
                    return [$name => $cp1];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::warning('No se pudo cargar pulpa por variedad desde SQLSRV', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function getPlanningLogoDataUri(): ?string
    {
        try {
            $logoPath = public_path('img/logogreenex.png');
            if (! is_file($logoPath)) {
                return null;
            }
            $bin = @file_get_contents($logoPath);
            if (! is_string($bin) || $bin === '') {
                return null;
            }
            return 'data:image/png;base64,'.base64_encode($bin);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Renderiza el instructivo usando el HTML de referencia (instructivo-proceso.html).
     *
     * Meta: mantener exactamente la misma estructura visual (CSS, tabla “dataframe”, títulos).
     *
     * @param array<int, array<string, mixed>> $lineSheets
     */
    private function renderInstructionFromHtmlTemplate(string $templatePath, array $lineSheets, string $date, ?Shift $shift, array $metaByLineId = []): string
    {
        $tpl = @file_get_contents($templatePath);
        if (! is_string($tpl) || trim($tpl) === '') {
            throw new \RuntimeException('No se pudo leer instructivo-proceso.html');
        }

        // Usamos el CSS del template (para asegurar “mismo formato”).
        $style = '';
        if (preg_match('/<style>(.*?)<\\/style>/si', $tpl, $m)) {
            $style = (string) ($m[1] ?? '');
        }

        $title = 'instructivo-proceso';
        if (preg_match('/<title>(.*?)<\\/title>/si', $tpl, $m)) {
            $title = trim((string) ($m[1] ?? $title)) ?: $title;
        }

        // Header con logo (evitar textos de plantilla tipo “instructivo-proceso.xlsx / Contenido / ...”).
        $logoDataUri = null;
        try {
            $logoPath = public_path('img/logogreenex.png');
            if (is_file($logoPath)) {
                $bin = @file_get_contents($logoPath);
                if (is_string($bin) && $bin !== '') {
                    $logoDataUri = 'data:image/png;base64,'.base64_encode($bin);
                }
            }
        } catch (\Throwable) {
            $logoDataUri = null;
        }

        // CSS extra para un instructivo más legible en planta.
        $extraCss = "\n"
            .".meta-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:12px}\n"
            ."@media (max-width: 900px){.meta-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}\n"
            .".meta-box{border:1px solid #e5e5e5;border-radius:10px;padding:10px;background:#fff}\n"
            .".meta-label{font-size:11px;color:#6b7280;margin-bottom:2px}\n"
            .".meta-value{font-weight:700;color:#111827;font-size:13px}\n"
            .".section-h3{margin:10px 0 6px 0;font-weight:800;color:#111827;font-size:13px}\n"
            ."table.simple{width:100%}\n"
            ."table.simple th{background:#f3f4f6;text-align:left}\n"
            ."table.simple th, table.simple td{font-size:11px}\n"
            ."table.simple tr.total-row td{background:#eef2ff;font-weight:800}\n"
            ."table.simple tr.warn-row td{background:#fff7ed}\n"
            .".doc-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 12px 0;padding:10px 12px;border:1px solid #e5e5e5;border-radius:12px;background:linear-gradient(90deg,#ffffff,#f8fafc)}\n"
            .".doc-header .title{font-weight:900;font-size:14px;color:#111827}\n"
            .".doc-header .subtitle{font-size:11px;color:#6b7280;margin-top:2px}\n"
            .".doc-header img{height:34px;width:auto;object-fit:contain}\n"
            .".line-title{margin:10px 0 8px 0;font-weight:900;color:#111827;font-size:13px}\n"
            ."@media print{body{margin:12px}.table-wrap{border:none;padding:0}}\n";

        $sections = [];
        foreach (array_values($lineSheets) as $idx => $sheetData) {
            $lineName = (string) ($sheetData['lineName'] ?? ('Línea '.($idx + 1)));
            $sectionId = 'linea-'.$idx;
            $sectionLabel = $lineName;

            $tableHtml = $this->renderInstructionBlockFromLineSheet($sheetData, $date, $shift);

            $lineId = (int) ($sheetData['lineId'] ?? 0);
            $meta = $lineId > 0 && isset($metaByLineId[$lineId]) ? (array) $metaByLineId[$lineId] : null;

            $sections[] = [
                'id' => $sectionId,
                'label' => $sectionLabel,
                'lineId' => $lineId,
                'meta' => $meta,
                'table' => $tableHtml,
            ];
        }

        $body = '';
        foreach ($sections as $i => $s) {
            $body .= "<div id='".$this->escapeHtml($s['id'])."'>\n";
            $versionTxt = '';
            $meta = is_array($s['meta'] ?? null) ? $s['meta'] : null;
            if ($meta && isset($meta['version']) && (int) $meta['version'] > 0) {
                $versionTxt = ' · Versión '.((int) $meta['version']);
            }

            $body .= "<div class='line-title'>Línea/Cámara: ".$this->escapeHtml($s['label']).$this->escapeHtml($versionTxt)."</div>\n";
            if ($meta && (! empty($meta['changed_by_name']) || ! empty($meta['changed_at']) || ! empty($meta['reason']))) {
                $mini = [];
                if (! empty($meta['changed_at'])) {
                    $mini[] = 'Modificado: '.(string) $meta['changed_at'];
                }
                if (! empty($meta['changed_by_name'])) {
                    $mini[] = 'Por: '.(string) $meta['changed_by_name'];
                }
                if (! empty($meta['reason'])) {
                    $mini[] = 'Motivo: '.(string) $meta['reason'];
                }
                $body .= "<div class='subtitle' style='margin:0 0 8px 0'>".$this->escapeHtml(implode(' · ', $mini))."</div>\n";
            }
            $body .= "<div class='table-wrap'>\n".$s['table']."\n</div>\n";
            $body .= "</div>\n";
            if ($i < (count($sections) - 1)) {
                // Útil para PDF: separa por página.
                $body .= "<div style='page-break-after:always'></div>\n";
            }
        }

        $headerDate = Carbon::parse($date, 'America/Santiago')->format('d-m-Y');
        $shiftLabel = '-';
        if ($shift) {
            $shiftLabel = trim(($shift->codigo ? (string) $shift->codigo : '').($shift->nombre ? (' '.$shift->nombre) : ''));
            $shiftLabel = $shiftLabel !== '' ? $shiftLabel : '-';
        }

        $headerExtra = '';
        $headerMeta = count($sections) === 1 ? (is_array($sections[0]['meta'] ?? null) ? $sections[0]['meta'] : null) : null;
        if ($headerMeta && isset($headerMeta['version']) && (int) $headerMeta['version'] > 0) {
            $headerExtra .= ' · Versión: '.((int) $headerMeta['version']);
        }

        $logoHtml = $logoDataUri
            ? "<img src='".$this->escapeHtml($logoDataUri)."' alt='Greenex' />"
            : "<div class='title'>GREENEX</div>";

        return "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>\n"
            ."<title>".$this->escapeHtml($title)."</title>\n\n"
            ."<style>\n".$style."\n".$extraCss."\n</style>\n\n"
            ."</head><body>\n"
            ."<div class='doc-header'>"
            .$logoHtml
            ."<div style='flex:1;min-width:0'>"
            ."<div class='title'>INSTRUCTIVO DE EMBALAJE</div>"
            ."<div class='subtitle'>Fecha: ".$this->escapeHtml($headerDate)." · Turno: ".$this->escapeHtml($shiftLabel).$this->escapeHtml($headerExtra)."</div>"
            ."</div>"
            ."</div>\n"
            .$body
            ."</body></html>";
    }

    /**
     * @param array<string, mixed> $sheetData
     */
    private function renderInstructionBlockFromLineSheet(array $sheetData, string $date, ?Shift $shift): string
    {
        $tz = 'America/Santiago';

        $lineName = (string) ($sheetData['lineName'] ?? 'LINEA');
        $speciesLabel = (string) ($sheetData['speciesLabel'] ?? '');
        $kilos = (float) ($sheetData['kilos'] ?? 0);
        $exportadoraLabel = $sheetData['exportadoraLabel'] ?? null;
        $pedidosLabel = $sheetData['pedidosLabel'] ?? null;
        $lots = $sheetData['lots'] ?? [];
        $packagingSummary = $sheetData['packagingSummary'] ?? [];

        $shiftLabel = '-';
        if ($shift) {
            $shiftLabel = trim(($shift->codigo ? (string) $shift->codigo : '').($shift->nombre ? (' '.$shift->nombre) : ''));
            $shiftLabel = $shiftLabel !== '' ? $shiftLabel : '-';
        }

        $camera = $lineName;
        if (preg_match('/(\\d+)/', $lineName, $m)) {
            $camera = (string) $m[1];
        }

        $fechaProceso = Carbon::parse($date, $tz)->format('d-m-Y');

        // META
        $meta = "<div class='meta-grid'>\n"
            ."<div class='meta-box'><div class='meta-label'>Especie</div><div class='meta-value'>".$this->escapeHtml($speciesLabel !== '' ? $speciesLabel : 'VARIAS')."</div></div>\n"
            ."<div class='meta-box'><div class='meta-label'>Exportadora</div><div class='meta-value'>".$this->escapeHtml((string) ($exportadoraLabel ?: '-'))."</div></div>\n"
            ."<div class='meta-box'><div class='meta-label'>Fecha proceso</div><div class='meta-value'>".$this->escapeHtml($fechaProceso)."</div></div>\n"
            ."<div class='meta-box'><div class='meta-label'>Turno</div><div class='meta-value'>".$this->escapeHtml($shiftLabel)."</div></div>\n"
            ."<div class='meta-box'><div class='meta-label'>Línea/Cámara</div><div class='meta-value'>".$this->escapeHtml($lineName)."</div></div>\n"
            ."<div class='meta-box'><div class='meta-label'>Cámara</div><div class='meta-value'>".$this->escapeHtml($camera)."</div></div>\n"
            ."<div class='meta-box'><div class='meta-label'>Kilos</div><div class='meta-value'>".$this->escapeHtml((string) ((int) round($kilos)))."</div></div>\n"
            ."<div class='meta-box' style='grid-column: span 6;'><div class='meta-label'>Pedidos</div><div class='meta-value'>".$this->escapeHtml((string) ($pedidosLabel ?: '-'))."</div></div>\n"
            ."</div>";

        // LOTES (sin columna Envase)
        $lotHeaders = [
            'Hr inicio',
            'N° proceso',
            'Lote',
            'Tipo proceso',
            'Variedad original',
            'Productor',
            'CSG',
            'Categoría',
            'Fecha recepción',
            'Cantidad (bins)',
            'Kilos',
            'SDP',
            'Nota calidad',
            'Variedad rotulada',
            'Hrs estimadas',
        ];

        $lotRows = '';
        $sumBins = 0;
        $sumKilos = 0.0;
        foreach ($lots as $lot) {
            /** @var PackingProcessLot|mixed $lot */
            $start = $lot?->getAttribute('instruction_inicio') ?: ($lot?->inicio_estimado ?? null);
            $end = $lot?->getAttribute('instruction_fin') ?: ($lot?->fin_estimado ?? null);

            $startStr = $start ? Carbon::parse($start)->tz($tz)->format('H:i:s') : '';

            $durTxt = '';
            if ($start && $end) {
                $minutes = Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
                $h = intdiv($minutes, 60);
                $m = $minutes % 60;
                $durTxt = $h.':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            }

            $bins = (int) ($lot?->cantidad_bins ?? 0);
            $peso = (float) ($lot?->peso_neto ?? 0);
            $sumBins += $bins;
            $sumKilos += $peso;

            $fechaRec = $lot?->fecha_recepcion ? Carbon::parse((string) $lot->fecha_recepcion)->format('d-m-Y') : '';

            $cells = [
                $startStr,
                (string) ($lot?->process_id ?? ''),
                (string) ($lot?->n_g_recepcion ?? ''),
                (string) (($lot?->tipo_proceso ?? '') !== '' ? $lot->tipo_proceso : 'Normal'),
                (string) ($lot?->variedad_original ?? ''),
                (string) (($lot?->productor_real ?? '') !== '' ? $lot->productor_real : ($lot?->n_productor ?? '')),
                (string) ($lot?->csg_productor ?? ''),
                (string) ($lot?->categoria_origen ?? ''),
                $fechaRec,
                (string) $bins,
                (string) ((int) round($peso)),
                (string) ($lot?->sdp_centrocosto ?? ''),
                (string) ($lot?->setup_nota_calidad ?? ''),
                (string) ($lot?->n_variedad ?? ''),
                $durTxt,
            ];

            $lotRows .= "<tr>\n";
            foreach ($cells as $c) {
                $lotRows .= "<td>".$this->escapeHtml((string) $c)."</td>\n";
            }
            $lotRows .= "</tr>\n";
        }

        if (trim($lotRows) === '') {
            $lotRows = "<tr class='warn-row'><td colspan='".count($lotHeaders)."'>Sin lotes.</td></tr>\n";
        }

        $lotRows .= "<tr class='total-row'>"
            ."<td colspan='9'>TOTAL</td>"
            ."<td>".$this->escapeHtml((string) $sumBins)."</td>"
            ."<td>".$this->escapeHtml((string) ((int) round($sumKilos)))."</td>"
            ."<td colspan='4'></td>"
            ."</tr>\n";

        $lotThead = "<thead><tr>".implode('', array_map(fn ($h) => '<th>'.$this->escapeHtml($h).'</th>', $lotHeaders))."</tr></thead>";
        $lotsTable = "<div class='section-h3'>Procesos / lotes</div>\n"
            ."<table class='simple' border='1'>\n{$lotThead}\n<tbody>\n{$lotRows}</tbody>\n</table>";

        // EMBALAJES (llenar desde matriz + catálogo)
        $packHeaders = [
            'Destino',
            'Código embalaje',
            'Descripcion de Embalaje',
            'Etiqueta',
            'Peso Estandar',
            'Envases/Pallet',
            'Altura',
            'Calibres',
            'Indicaciones',
            'Observaciones',
            'count',
            'Pedido',
        ];

        $packRows = '';
        foreach ($packagingSummary as $row) {
            $rule = $row['rule'] ?? null;
            $override = is_array($row['override'] ?? null) ? $row['override'] : null;

            $destinoFinal = (string) ($row['destino'] ?? '');
            if ($override && ! empty($override['destino'])) {
                $destinoFinal = (string) $override['destino'];
            }

            $codeFinal = (string) ($row['c_item'] ?? '');
            if ($override && ! empty($override['c_item'])) {
                $codeFinal = (string) $override['c_item'];
            }

            // Descripción: se toma del catálogo SQLSRV (n_item). Fallback a la regla DB (desc_embalaje).
            $desc = (string) ($row['n_item'] ?? '');
            if (trim($desc) === '' && $rule?->desc_embalaje) {
                $desc = (string) $rule->desc_embalaje;
            }
            if ($override && ! empty($override['desc_embalaje'])) {
                $desc = (string) $override['desc_embalaje'];
            }

            $etiquetaFinal = (string) ($row['etiqueta'] ?? '');
            if ($override && ! empty($override['etiqueta'])) {
                $etiquetaFinal = (string) $override['etiqueta'];
            }

            $cp2Final = (string) ($row['cp2'] ?? '');
            if ($override && array_key_exists('cp2', $override) && $override['cp2'] !== null && $override['cp2'] !== '') {
                $cp2Final = (string) $override['cp2'];
            }

            $alturaFinal = (string) ($row['altura'] ?? '');
            if ($override && ! empty($override['altura'])) {
                $alturaFinal = (string) $override['altura'];
            }

            $pesoCaja = $rule?->peso_caja ?? null;
            if ($override && array_key_exists('peso_caja', $override) && $override['peso_caja'] !== null && $override['peso_caja'] !== '') {
                if (is_numeric($override['peso_caja'])) {
                    $pesoCaja = (float) $override['peso_caja'];
                }
            }
            $allowed = is_array($rule?->allowed_calibres) ? $rule->allowed_calibres : [];

            $calibresResumen = '';
            $numeric = [];
            foreach ($allowed as $a) {
                $a = trim((string) $a);
                if ($a === '') continue;
                if (is_numeric($a)) $numeric[] = (float) $a;
            }
            if (! empty($numeric)) {
                $calibresResumen = ((int) min($numeric)).' AL '.((int) max($numeric));
            } elseif (! empty($allowed)) {
                $calibresResumen = implode(', ', array_map('strval', $allowed));
            }
            if ($override && ! empty($override['calibres'])) {
                $calibresResumen = (string) $override['calibres'];
            }

            $obs = trim((string) ($rule?->calibres_note ?? ''));
            $sobre = trim((string) ($rule?->sobre_calibre_note ?? ''));
            if ($sobre !== '') {
                $obs = ($obs !== '' ? ($obs.' · ') : '').$sobre;
            }
            $obsFinal = $obs !== '' ? $obs : '';
            if ($override && ! empty($override['observaciones'])) {
                $obsFinal = (string) $override['observaciones'];
            }

            $indFinal = '';
            if (isset($row['indications']) && is_string($row['indications']) && trim((string) $row['indications']) !== '') {
                $indFinal = trim((string) $row['indications']);
            }
            if ($override && ! empty($override['indications'])) {
                $indFinal = (string) $override['indications'];
            }

            $pedidoFinal = '';
            if ($override && ! empty($override['pedido'])) {
                $pedidoFinal = (string) $override['pedido'];
            }

            $bins = (int) ($row['cantidad_bins'] ?? 0);
            $kgs = (float) ($row['kilos'] ?? 0);
            $countTxt = ($bins > 0 || $kgs > 0) ? ('Bins: '.$bins.' · Kg: '.((int) round($kgs))) : '';
            if ($override && ! empty($override['count'])) {
                $countTxt = (string) $override['count'];
            }

            $cells = [
                (string) ($destinoFinal ?? ''),
                (string) ($codeFinal ?? ''),
                (string) ($desc ?: ''),
                (string) ($etiquetaFinal ?? ''),
                $pesoCaja !== null ? (string) $pesoCaja : '',
                (string) ($cp2Final ?? ''),
                (string) ($alturaFinal ?? ''),
                (string) ($calibresResumen ?: ''),
                (string) ($indFinal ?: ''),
                (string) ($obsFinal ?: ''),
                $countTxt,
                (string) ($pedidoFinal ?: ''),
            ];

            $packRows .= "<tr>\n";
            foreach ($cells as $c) {
                $packRows .= "<td>".$this->escapeHtml((string) $c)."</td>\n";
            }
            $packRows .= "</tr>\n";
        }

        if (trim($packRows) === '') {
            $packRows = "<tr class='warn-row'><td colspan='".count($packHeaders)."'>Sin embalajes sugeridos (falta destino y/o embalaje en los lotes).</td></tr>\n";
        }

        $packThead = "<thead><tr>".implode('', array_map(fn ($h) => '<th>'.$this->escapeHtml($h).'</th>', $packHeaders))."</tr></thead>";
        $packTable = "<div class='section-h3'>Destino + Embalajes</div>\n"
            ."<table class='simple' border='1'>\n{$packThead}\n<tbody>\n{$packRows}</tbody>\n</table>";

        $comments = "<div class='section-h3'>Comentarios</div>\n"
            ."<div class='meta-box'><div class='meta-value'>".$this->escapeHtml('Camara '.$camera.'/')."</div></div>";

        return $meta."\n".$lotsTable."\n".$packTable."\n".$comments;
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Renderiza el instructivo usando el XLSX real como plantilla (para igualar formato visual).
     *
     * @param array<int, array<string, mixed>> $lineSheets
     */
    private function renderInstructionFromXlsxTemplate(string $templatePath, array $lineSheets, string $date, ?Shift $shift): string
    {
        $spreadsheet = IOFactory::load($templatePath);
        $templateSheet = $spreadsheet->getActiveSheet();

        $safeTitle = function (string $name): string {
            $name = trim($name);
            $name = preg_replace('/[\\[\\]\\:\\*\\?\\/\\\\]/', '-', $name) ?: 'LINEA';
            $name = mb_substr($name, 0, 31);
            return $name !== '' ? $name : 'LINEA';
        };

        // Nos aseguramos de tener exactamente N sheets (una por línea).
        foreach (array_values($lineSheets) as $idx => $sheetData) {
            $sheet = $idx === 0 ? $templateSheet : (clone $templateSheet);
            if ($idx > 0) {
                $spreadsheet->addSheet($sheet);
            }

            $lineName = (string) ($sheetData['lineName'] ?? 'LINEA');
            $sheet->setTitle($safeTitle($lineName));

            $speciesLabel = (string) ($sheetData['speciesLabel'] ?? '');
            $kilos = (float) ($sheetData['kilos'] ?? 0);
            $lots = $sheetData['lots'] ?? [];
            $packagingSummary = $sheetData['packagingSummary'] ?? [];

            $shiftLabel = '-';
            if ($shift) {
                $shiftLabel = trim(($shift->codigo ? (string) $shift->codigo : '').($shift->nombre ? (' '.$shift->nombre) : ''));
                $shiftLabel = $shiftLabel !== '' ? $shiftLabel : '-';
            }

            // Meta (según plantilla observada):
            // B4 "Especie" / D4 valor
            // F4 "Fecha Proceso" / G4 valor
            // I4 "Versión" / J4 valor
            // B5 "Turno" / D5 valor
            // F5 "Línea Proceso" / G5 valor
            // I5 "Kilos" / J5 valor
            // F6 "CAMARA" / G6 valor
            $sheet->setCellValue('D4', $speciesLabel ?: 'VARIAS');
            $sheet->setCellValue('G4', Carbon::parse($date)->format('d-m-Y'));
            $sheet->setCellValue('J4', 1);
            $sheet->setCellValue('D5', $shiftLabel);
            $sheet->setCellValue('G5', $lineName);
            $sheet->setCellValue('J5', (int) round($kilos));

            $camera = $lineName;
            if (preg_match('/(\\d+)/', $lineName, $m)) {
                $camera = (string) $m[1];
            }
            $sheet->setCellValue('G6', $camera);

            // Sección de lotes:
            $firstLotRow = 9;
            $existingLotRows = 2; // plantilla trae 2 filas (9 y 10) antes del total
            $neededLotRows = max(1, is_countable($lots) ? count($lots) : 1);

            // Ajuste de filas para lotes (insert/remove antes del total).
            if ($neededLotRows > $existingLotRows) {
                $sheet->insertNewRowBefore($firstLotRow + $existingLotRows, $neededLotRows - $existingLotRows);
            } elseif ($neededLotRows < $existingLotRows) {
                $sheet->removeRow($firstLotRow + $neededLotRows, $existingLotRows - $neededLotRows);
            }

            $totalRow = $firstLotRow + $neededLotRows;
            $packHeaderRow = $totalRow + 2;
            $firstPackRow = $packHeaderRow + 1;

            // Estilo base de fila de lote (A9:S9).
            $lotStyle = $sheet->getStyle('A9:S9');
            $lotHeight = $sheet->getRowDimension(9)->getRowHeight();

            // Limpiar valores previos en rango de lotes.
            $sheet->fromArray(array_fill(0, 19, null), null, 'A'.$firstLotRow);
            for ($r = $firstLotRow; $r < $totalRow; $r++) {
                $sheet->duplicateStyle($lotStyle, "A{$r}:S{$r}");
                if ($lotHeight !== null) {
                    $sheet->getRowDimension($r)->setRowHeight($lotHeight);
                }
            }

            $sumBins = 0;
            foreach (array_values($lots) as $i => $lot) {
                $r = $firstLotRow + $i;
                $ng = is_object($lot) ? ($lot->n_g_recepcion ?? null) : ($lot['n_g_recepcion'] ?? null);
                $processId = is_object($lot) ? ($lot->process_id ?? null) : ($lot['process_id'] ?? null);
                $tipoProceso = is_object($lot) ? ($lot->tipo_proceso ?? null) : ($lot['tipo_proceso'] ?? null);
                $varOriginal = is_object($lot) ? ($lot->variedad_original ?? null) : ($lot['variedad_original'] ?? null);
                $prodReal = is_object($lot) ? ($lot->productor_real ?? null) : ($lot['productor_real'] ?? null);
                $csg = is_object($lot) ? ($lot->csg_productor ?? null) : ($lot['csg_productor'] ?? null);
                $cat = is_object($lot) ? ($lot->categoria_origen ?? null) : ($lot['categoria_origen'] ?? null);
                $fechaRec = is_object($lot) ? ($lot->fecha_recepcion ?? null) : ($lot['fecha_recepcion'] ?? null);
                $bins = (int) (is_object($lot) ? ($lot->cantidad_bins ?? 0) : ($lot['cantidad_bins'] ?? 0));
                $peso = (float) (is_object($lot) ? ($lot->peso_neto ?? 0) : ($lot['peso_neto'] ?? 0));
                $sdp = is_object($lot) ? ($lot->sdp_centrocosto ?? null) : ($lot['sdp_centrocosto'] ?? null);
                $envase = is_object($lot) ? ($lot->envase_origen ?? null) : ($lot['envase_origen'] ?? null);
                $nota = is_object($lot) ? ($lot->setup_nota_calidad ?? null) : ($lot['setup_nota_calidad'] ?? null);
                $varRot = is_object($lot) ? ($lot->n_variedad ?? null) : ($lot['n_variedad'] ?? null);

                $inicio = is_object($lot) ? ($lot->instruction_inicio ?? null) : ($lot['instruction_inicio'] ?? null);
                $fin = is_object($lot) ? ($lot->instruction_fin ?? null) : ($lot['instruction_fin'] ?? null);
                $durTxt = '-';
                if ($inicio && $fin) {
                    $start = $inicio instanceof Carbon ? $inicio : Carbon::parse((string) $inicio);
                    $end = $fin instanceof Carbon ? $fin : Carbon::parse((string) $fin);
                    $mins = $start->diffInMinutes($end);
                    $durTxt = sprintf('%d:%02d', intdiv($mins, 60), $mins % 60);
                }
                $startTxt = $inicio ? (Carbon::parse((string) $inicio)->format('H:i:s')) : '-';

                $sheet->setCellValue("A{$r}", $startTxt);
                $sheet->setCellValue("B{$r}", (string) ($processId ?? ''));
                $sheet->setCellValue("C{$r}", (string) ($ng ?? ''));
                $sheet->setCellValue("D{$r}", (string) ($tipoProceso ?: 'Normal'));
                $sheet->setCellValue("E{$r}", (string) ($varOriginal ?: ''));
                $sheet->setCellValue("F{$r}", (string) ($prodReal ?: ''));
                $sheet->setCellValue("G{$r}", (string) ($csg ?: ''));
                $sheet->setCellValue("H{$r}", (string) ($cat ?: ''));
                $sheet->setCellValue("I{$r}", $fechaRec ? Carbon::parse((string) $fechaRec)->format('d-m-Y') : '');
                $sheet->setCellValue("J{$r}", $bins);
                $sheet->setCellValue("K{$r}", (int) round($peso));
                $sheet->setCellValue("L{$r}", (string) ($sdp ?: ''));
                $sheet->setCellValue("M{$r}", (string) ($envase ?: ''));
                $sheet->setCellValue("N{$r}", (string) ($nota ?: ''));
                $sheet->setCellValue("O{$r}", '');
                $sheet->setCellValue("P{$r}", '');
                $sheet->setCellValue("Q{$r}", (string) ($varRot ?: ''));
                $sheet->setCellValue("S{$r}", $durTxt);

                $sumBins += $bins;
            }

            // Totales (J = cantidad, K = kilos)
            $sheet->setCellValue("J{$totalRow}", 0);
            $sheet->setCellValue("K{$totalRow}", (int) round($kilos));

            // Sección packaging: localizar comentarios (col D) en la hoja para limpiar/rellenar.
            $highest = (int) $sheet->getHighestRow();
            $commentsRow = null;
            for ($r = $firstPackRow; $r <= $highest; $r++) {
                $val = $sheet->getCell("D{$r}")->getValue();
                if (is_string($val) && str_starts_with(trim($val), 'Comentarios')) {
                    $commentsRow = $r;
                    break;
                }
            }
            if ($commentsRow === null) {
                $commentsRow = $packHeaderRow + 6; // fallback razonable
            }

            $packStyle = $sheet->getStyle("D{$firstPackRow}:R{$firstPackRow}");
            $packHeight = $sheet->getRowDimension($firstPackRow)->getRowHeight();

            $removeCount = max(0, ($commentsRow - $firstPackRow));
            if ($removeCount > 0) {
                $sheet->removeRow($firstPackRow, $removeCount);
            }

            $packCount = is_countable($packagingSummary) ? count($packagingSummary) : 0;
            $insertCount = $packCount + 1; // +1 fila en blanco antes de comentarios
            if ($insertCount > 0) {
                $sheet->insertNewRowBefore($firstPackRow, $insertCount);
            }

            for ($i = 0; $i < $packCount; $i++) {
                $r = $firstPackRow + $i;
                $sheet->duplicateStyle($packStyle, "D{$r}:R{$r}");
                if ($packHeight !== null) {
                    $sheet->getRowDimension($r)->setRowHeight($packHeight);
                }

                $row = $packagingSummary[$i] ?? [];
                $rule = $row['rule'] ?? null;
                $desc = $rule?->desc_embalaje ?? ($row['n_item'] ?? '');
                $peso = $rule?->peso_caja ?? null;
                $nota = $rule?->nota ?? null;
                $allowed = is_array($rule?->allowed_calibres) ? $rule->allowed_calibres : [];
                $calibresResumen = '-';
                $numeric = [];
                foreach ($allowed as $a) {
                    $a = trim((string) $a);
                    if ($a === '') {
                        continue;
                    }
                    if (is_numeric($a)) {
                        $numeric[] = (float) $a;
                    }
                }
                if (! empty($numeric)) {
                    $calibresResumen = ((int) min($numeric)).' AL '.((int) max($numeric));
                } elseif (! empty($allowed)) {
                    $calibresResumen = implode(', ', array_map('strval', $allowed));
                }

                $obs = trim((string) ($rule?->calibres_note ?? ''));
                $sobre = trim((string) ($rule?->sobre_calibre_note ?? ''));
                if ($sobre !== '') {
                    $obs = ($obs !== '' ? ($obs.' · ') : '').$sobre;
                }

                $sheet->setCellValue("D{$r}", (string) ($row['destino'] ?? ''));
                $sheet->setCellValue("E{$r}", (string) ($row['c_item'] ?? ''));
                $sheet->setCellValue("F{$r}", (string) ($desc ?: ''));
                $sheet->setCellValue("G{$r}", (string) ($row['etiqueta'] ?? ''));
                $sheet->setCellValue("H{$r}", $peso !== null ? (string) $peso : '');
                $sheet->setCellValue("I{$r}", (string) ($row['cp2'] ?? ''));
                $sheet->setCellValue("J{$r}", (string) ($row['altura'] ?? ''));
                $sheet->setCellValue("K{$r}", $calibresResumen);
                $sheet->setCellValue("L{$r}", (string) ($nota ?: ''));
                $sheet->setCellValue("M{$r}", (string) ($obs ?: ''));
                $sheet->setCellValue("Q{$r}", '');
                $sheet->setCellValue("R{$r}", '');
            }

            $commentsRow = $firstPackRow + $insertCount;
            $sheet->setCellValue("D{$commentsRow}", 'Comentarios: Camara '.$camera.'/');
        }

        $writer = new SpreadsheetHtmlWriter($spreadsheet);
        $writer->setGenerateSheetNavigationBlock(false);
        $writer->writeAllSheets();

        ob_start();
        $writer->save('php://output');
        return (string) ob_get_clean();
    }
}
