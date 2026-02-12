<?php

namespace App\Http\Controllers\Planning;

use App\Enums\PlanningProcessStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\FolioDeduction;
use App\Models\PackingLine;
use App\Models\PackingLineMonitor;
use App\Models\PackingProcess;
use App\Models\PackingProcessLot;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PackingLineMonitorController extends Controller
{
    use AuthorizesPlanning;

    public function index(Request $request): Response
    {
        $this->authorizePlanning($request);

        $tz = 'America/Santiago';
        $today = Carbon::now($tz)->toDateString();

        $date = (string) $request->query('date', $today);
        $shiftId = $request->filled('shift_id') ? (int) $request->query('shift_id') : null;

        $shifts = Shift::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'horas', 'hora_inicio'])
            ->map(fn (Shift $s) => [
                'id' => (int) $s->id,
                'codigo' => (string) $s->codigo,
                'nombre' => (string) $s->nombre,
                'horas' => (float) ($s->horas ?? 0),
                'hora_inicio' => $s->hora_inicio ? (string) $s->hora_inicio : null,
            ])
            ->values();

        if (! $shiftId) {
            $shiftId = (int) ($shifts->first()['id'] ?? 0);
        }

        // Cámaras/Líneas activas: por defecto mostramos HAND_PACK (cámaras).
        $type = (string) $request->query('type', 'HAND_PACK');
        $linesQuery = PackingLine::query()->where('activo', true);
        if ($type !== 'ALL') {
            $linesQuery->where('tipo', $type);
        }
        $lines = $linesQuery->orderBy('nombre')->get(['id', 'nombre', 'tipo']);

        // Procesos del día/turno (para armar anterior/actual/siguiente por cámara).
        $statuses = [
            PlanningProcessStatus::CONFIRMADO->value,
            PlanningProcessStatus::EN_PROCESO->value,
            PlanningProcessStatus::CERRADO->value,
            PlanningProcessStatus::BORRADOR->value,
        ];

        $processIds = PackingProcess::query()
            ->whereDate('fecha', $date)
            ->where('shift_id', $shiftId)
            ->whereIn('estado', $statuses)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $lotsByLine = collect();
        if (! empty($processIds)) {
            $lotsByLine = PackingProcessLot::query()
                ->whereIn('process_id', $processIds)
                ->with(['process:id,especie,exportadora,estado,pedidos,fecha,shift_id', 'packingLine:id,nombre,tipo'])
                ->get()
                ->groupBy('packing_line_id');
        }

        $monitors = PackingLineMonitor::query()
            ->whereDate('fecha', $date)
            ->where('shift_id', $shiftId)
            ->whereIn('packing_line_id', $lines->pluck('id')->all())
            ->get()
            ->keyBy(fn (PackingLineMonitor $m) => (int) $m->packing_line_id);

        $monitorSqlsrvIds = $monitors
            ->pluck('sqlsrv_production_id')
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $deductionsSummary = collect();
        if (! empty($monitorSqlsrvIds)) {
            $deductionsSummary = FolioDeduction::query()
                ->whereIn('process_id', $monitorSqlsrvIds)
                ->selectRaw('process_id, count(*) as total, max(scanned_at) as last_scanned_at')
                ->groupBy('process_id')
                ->get()
                ->keyBy(fn ($r) => (int) $r->process_id);
        }

        $now = Carbon::now($tz);

        $cards = $lines->map(function (PackingLine $line) use ($lotsByLine, $monitors, $deductionsSummary, $now, $tz) {
            $lineLots = ($lotsByLine->get((int) $line->id) ?? collect())->values();

            $blocks = $lineLots
                ->groupBy('process_id')
                ->map(function ($lots) use ($tz) {
                    /** @var \Illuminate\Support\Collection<int,\App\Models\PackingProcessLot> $lots */
                    $p = $lots->first()?->process;

                    $start = $lots->pluck('inicio_estimado')->filter()->min();
                    $end = $lots->pluck('fin_estimado')->filter()->max();

                    $variedades = $lots
                        ->pluck('n_variedad')
                        ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                        ->map(fn ($v) => trim((string) $v))
                        ->unique()
                        ->values();

                    $destinos = $lots
                        ->pluck('destino')
                        ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                        ->map(fn ($v) => trim((string) $v))
                        ->unique()
                        ->values();

                    $lotes = $lots
                        ->pluck('n_g_recepcion')
                        ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                        ->map(fn ($v) => trim((string) $v))
                        ->unique()
                        ->values();

                    return [
                        'process_id' => (int) ($p?->id ?? 0),
                        'process_number' => (string) ((int) ($p?->id ?? 0)),
                        'estado' => $p?->estado?->value ?? (string) ($p?->estado ?? ''),
                        'especie' => (string) ($p?->especie ?? ''),
                        'exportadora' => is_string($p?->exportadora) ? (string) $p->exportadora : null,
                        'variedad' => $variedades->count() === 1 ? (string) $variedades->first() : ($variedades->count() > 1 ? 'VARIAS' : null),
                        'destino' => $destinos->count() === 1 ? (string) $destinos->first() : ($destinos->count() > 1 ? 'MÚLTIPLE' : null),
                        'lote' => $lotes->count() === 1 ? (string) $lotes->first() : ($lotes->count() > 1 ? implode(', ', $lotes->take(2)->all()).'... ('.$lotes->count().')' : null),
                        'pedidos' => is_string($p?->pedidos) ? (string) $p->pedidos : null,
                        'bins' => (int) round((float) $lots->sum(fn ($l) => (float) ($l->cantidad_bins ?? 0))),
                        'kilos' => (float) $lots->sum(fn ($l) => (float) ($l->peso_neto ?? 0)),
                        'inicio' => $start ? Carbon::parse($start)->tz($tz)->toDateTimeString() : null,
                        'fin' => $end ? Carbon::parse($end)->tz($tz)->toDateTimeString() : null,
                    ];
                })
                ->values()
                ->sortBy([
                    [fn ($b) => $b['inicio'] ? 0 : 1, 'asc'],
                    ['inicio', 'asc'],
                    ['process_id', 'asc'],
                ])
                ->values();

            $idxCurrent = null;
            foreach ($blocks as $i => $b) {
                if (! $b['inicio'] || ! $b['fin']) {
                    continue;
                }
                $s = Carbon::parse($b['inicio'], $tz);
                $e = Carbon::parse($b['fin'], $tz);
                if ($now->betweenIncluded($s, $e)) {
                    $idxCurrent = (int) $i;
                    break;
                }
            }
            if ($idxCurrent === null) {
                $idxNext = null;
                foreach ($blocks as $i => $b) {
                    if (! $b['inicio']) continue;
                    $s = Carbon::parse($b['inicio'], $tz);
                    if ($s->greaterThan($now)) {
                        $idxNext = (int) $i;
                        break;
                    }
                }
                $idxCurrent = $idxNext !== null ? $idxNext : (count($blocks) ? max(0, count($blocks) - 1) : null);
            }

            $prev = ($idxCurrent !== null && $idxCurrent > 0) ? $blocks[$idxCurrent - 1] : null;
            $cur = ($idxCurrent !== null && isset($blocks[$idxCurrent])) ? $blocks[$idxCurrent] : null;
            $next = ($idxCurrent !== null && isset($blocks[$idxCurrent + 1])) ? $blocks[$idxCurrent + 1] : null;

            $monitor = $monitors->get((int) $line->id);
            $sqlsrvId = $monitor && is_numeric($monitor->sqlsrv_production_id) ? (int) $monitor->sqlsrv_production_id : null;
            $sum = $sqlsrvId ? $deductionsSummary->get($sqlsrvId) : null;
            $deducted = $sum ? (int) ($sum->total ?? 0) : 0;

            return [
                'line' => [
                    'id' => (int) $line->id,
                    'nombre' => (string) $line->nombre,
                    'tipo' => $line->tipo?->value ?? (string) $line->tipo,
                ],
                'blocks' => [
                    'prev' => $prev,
                    'current' => $cur,
                    'next' => $next,
                ],
                'monitor' => [
                    'sqlsrv_production_id' => $sqlsrvId,
                    'sqlsrv_production_number' => $monitor?->sqlsrv_production_number ? (string) $monitor->sqlsrv_production_number : null,
                    'deducted_bins' => $deducted,
                    'last_scanned_at' => $sum && $sum->last_scanned_at ? Carbon::parse($sum->last_scanned_at)->tz($tz)->toDateTimeString() : null,
                ],
            ];
        })->values();

        return Inertia::render('Planning/Cameras/Index', [
            'filters' => [
                'date' => $date,
                'shift_id' => $shiftId,
                'type' => $type,
            ],
            'shifts' => $shifts,
            'cards' => $cards,
        ]);
    }

    public function bindSqlsrvProcess(Request $request): RedirectResponse
    {
        $this->authorizePlanning($request);

        $validated = $request->validate([
            'packing_line_id' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'shift_id' => ['required', 'integer', 'min:1'],
            'process_number' => ['required', 'string', 'max:60'],
        ]);

        $processNumber = trim((string) $validated['process_number']);

        $produccion = DB::connection('sqlsrv')
            ->table('PKG_G_Produccion')
            ->where('numero_i', $processNumber)
            ->select('id', 'numero_i')
            ->first();

        if (! $produccion) {
            return back()->with('error', "No existe el proceso SQL (PKG_G_Produccion.numero_i) {$processNumber}.");
        }

        PackingLineMonitor::query()->updateOrCreate(
            [
                'packing_line_id' => (int) $validated['packing_line_id'],
                'fecha' => (string) $validated['date'],
                'shift_id' => (int) $validated['shift_id'],
            ],
            [
                'sqlsrv_production_id' => (int) $produccion->id,
                'sqlsrv_production_number' => (string) $produccion->numero_i,
                'linked_by' => $request->user()?->id,
                'linked_at' => now(),
            ],
        );

        return back()->with('success', 'Proceso SQL vinculado a la cámara.');
    }

    public function live(Request $request): JsonResponse
    {
        $this->authorizePlanning($request);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'shift_id' => ['required', 'integer', 'min:1'],
            'line_ids' => ['nullable', 'array'],
            'line_ids.*' => ['integer', 'min:1'],
        ]);

        $tz = 'America/Santiago';

        $q = PackingLineMonitor::query()
            ->whereDate('fecha', (string) $validated['date'])
            ->where('shift_id', (int) $validated['shift_id']);

        if (! empty($validated['line_ids'])) {
            $q->whereIn('packing_line_id', array_map('intval', $validated['line_ids']));
        }

        $monitors = $q->get();

        $sqlsrvIds = $monitors
            ->pluck('sqlsrv_production_id')
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $summary = [];
        $recentByProcess = [];

        if (! empty($sqlsrvIds)) {
            $totals = FolioDeduction::query()
                ->whereIn('process_id', $sqlsrvIds)
                ->selectRaw('process_id, count(*) as total, max(scanned_at) as last_scanned_at')
                ->groupBy('process_id')
                ->get()
                ->keyBy(fn ($r) => (int) $r->process_id);

            $recent = FolioDeduction::query()
                ->whereIn('process_id', $sqlsrvIds)
                ->with(['user:id,name'])
                ->orderByDesc('scanned_at')
                ->limit(80)
                ->get(['id', 'process_id', 'folio', 'user_id', 'scanned_at']);

            $recentByProcess = $recent
                ->groupBy('process_id')
                ->map(function ($items) use ($tz) {
                    return $items->take(8)->map(fn ($d) => [
                        'folio' => (string) $d->folio,
                        'user' => $d->user?->name ? (string) $d->user->name : null,
                        'scanned_at' => $d->scanned_at ? $d->scanned_at->tz($tz)->toDateTimeString() : null,
                    ])->values()->all();
                })
                ->all();

            foreach ($totals as $pid => $row) {
                $summary[(int) $pid] = [
                    'deducted_bins' => (int) ($row->total ?? 0),
                    'last_scanned_at' => $row->last_scanned_at ? Carbon::parse($row->last_scanned_at)->tz($tz)->toDateTimeString() : null,
                ];
            }
        }

        $out = [];
        foreach ($monitors as $m) {
            $pid = is_numeric($m->sqlsrv_production_id) ? (int) $m->sqlsrv_production_id : 0;
            $out[(int) $m->packing_line_id] = [
                'sqlsrv_production_id' => $pid ?: null,
                'sqlsrv_production_number' => $m->sqlsrv_production_number ? (string) $m->sqlsrv_production_number : null,
                'deducted_bins' => $pid && isset($summary[$pid]) ? $summary[$pid]['deducted_bins'] : 0,
                'last_scanned_at' => $pid && isset($summary[$pid]) ? $summary[$pid]['last_scanned_at'] : null,
                'recent' => $pid && isset($recentByProcess[$pid]) ? $recentByProcess[$pid] : [],
            ];
        }

        return response()->json([
            'ok' => true,
            'data' => $out,
        ]);
    }
}
