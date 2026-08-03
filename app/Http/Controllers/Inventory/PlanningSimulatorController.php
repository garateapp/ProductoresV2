<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Inventory\Concerns\AuthorizesInventory;
use App\Models\InventoryMaterial;
use App\Models\InventoryPackaging;
use App\Models\InventoryTechnicalSheet;
use App\Models\PackingLine;
use App\Models\PackingProcessLot;
use App\Models\Recepcion;
use App\Services\Inventory\TheoreticalConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;

class PlanningSimulatorController extends Controller
{
    use AuthorizesInventory;

    public function index(Request $request, TheoreticalConsumptionService $consumptionService): Response
    {
        $this->authorizeInventory($request);

        $calibreCurveRaw = $request->input('calibre_curve', '');
        $calibreCurve = $calibreCurveRaw ? collect(json_decode($calibreCurveRaw, true))->filter(fn ($r) => ! empty($r['calibre']) && (float) ($r['percentage'] ?? 0) > 0)->values()->all() : [];

        $filters = [
            'packing_line_id' => (string) $request->input('packing_line_id', ''),
            'packaging_id' => (string) $request->input('packaging_id', ''),
            'kilos' => (string) $request->input('kilos', ''),
            'mode' => (string) $request->input('mode', 'kilos'),
            'selected_lot_ids' => collect($request->input('selected_lot_ids', []))->map(fn ($id) => (int) $id)->values()->all(),
            'calibre_curve' => $calibreCurve,
        ];

        $lines = PackingLine::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'especie', 'especies'])
            ->map(fn (PackingLine $line) => [
                'id' => $line->id,
                'nombre' => $line->nombre,
                'tipo' => $line->tipo?->value ?? (string) $line->tipo,
                'especie' => $line->especie,
                'especies' => $line->especies ?? [],
            ])
            ->values();

        $packagings = InventoryPackaging::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'peso_std', 'cantidad_cajas'])
            ->map(fn (InventoryPackaging $packaging) => [
                'id' => $packaging->id,
                'codigo' => $packaging->codigo,
                'nombre' => $packaging->nombre,
                'peso_std' => (float) $packaging->peso_std,
                'cantidad_cajas' => (float) $packaging->cantidad_cajas,
            ])
            ->values();

        $processLots = [];
        if ($filters['mode'] === 'lotes' || $filters['packing_line_id']) {
            $query = PackingProcessLot::query()
                //->whereIn('estado', ['pendiente', 'en_proceso', 'activo'])
                ->orderBy('orden')
                ->orderBy('inicio_estimado');

            if ($filters['packing_line_id']) {
                $query->where('packing_line_id', (int) $filters['packing_line_id']);
            }

            $processLots = $query
                ->limit(500)
                ->get(['id', 'setup_calibre', 'n_variedad', 'c_productor', 'n_productor', 'inicio_estimado', 'peso_neto', 'orden', 'estado', 'packing_line_id', 'n_g_recepcion'])
                ->map(fn ($lot) => [
                    'id' => $lot->id,
                    'calibre' => $lot->setup_calibre,
                    'variety' => $lot->n_variedad,
                    'producer_code' => $lot->c_productor,
                    'producer_name' => $lot->n_productor,
                    'estimated_start' => optional($lot->inicio_estimado)->format('Y-m-d H:i'),
                    'weight' => (float) ($lot->peso_neto ?? 0),
                    'order' => (int) ($lot->orden ?? 0),
                    'status' => $lot->estado?->value ?? (string) $lot->estado,
                    'line_id' => (int) $lot->packing_line_id,
                    'selected' => in_array((int) $lot->id, $filters['selected_lot_ids'], true),
                    'n_g_recepcion' => $lot->n_g_recepcion,
                ])
                ->values()
                ->all();

            $ngs = collect($processLots)->pluck('n_g_recepcion')->filter()->unique()->values()->all();
            if (! empty($ngs)) {
                $calibreDistributions = [];
                Recepcion::query()
                    ->whereIn('numero_g_recepcion', $ngs)
                    ->with(['calidad.detalles' => fn ($q) => $q->where('tipo_item', 'DISTRIBUCIÓN DE CALIBRES')->select(['id', 'calidad_id', 'detalle_item', 'porcentaje_muestra'])])
                    ->get()
                    ->each(function ($recepcion) use (&$calibreDistributions) {
                        $ng = (string) $recepcion->numero_g_recepcion;
                        $details = $recepcion->calidad?->detalles ?? collect();
                        $calibreDistributions[$ng] = $details->map(fn ($d) => [
                            'calibre' => trim((string) ($d->detalle_item ?? '')),
                            'percentage' => (float) ($d->porcentaje_muestra ?? 0),
                        ])->filter(fn ($c) => $c['calibre'] !== '' && $c['percentage'] > 0)->values()->all();
                    });

                $processLots = array_map(function ($lot) use ($calibreDistributions) {
                    $ng = $lot['n_g_recepcion'] ?? '';
                    $dist = $calibreDistributions[$ng] ?? [];
                    $lot['calibre_distribution'] = array_map(function ($c) use ($lot) {
                        $c['kilos'] = round($lot['weight'] * ($c['percentage'] / 100), 2);
                        return $c;
                    }, $dist);
                    return $lot;
                }, $processLots);
            }
        }
        Log::info('PlanningSimulatorController index', [
            'filters' => $filters,
            'process_lots_count' => count($processLots),
        ]);
        return Inertia::render('Inventory/PlanningSimulator/Index', [
            'filters' => $filters,
            'lines' => $lines,
            'packagings' => $packagings,
            'processLots' => $processLots,
            'simulation' => $this->buildSimulation($filters, $processLots, $consumptionService),
        ]);
    }

    private function buildSimulation(array $filters, array $processLots, TheoreticalConsumptionService $consumptionService): ?array
    {
        if (! $filters['packaging_id']) {
            return null;
        }

        $mode = $filters['mode'] ?? 'kilos';

        if ($mode !== 'lotes' && ! $filters['packing_line_id']) {
            return null;
        }

        $line = null;
        if ($filters['packing_line_id']) {
            $line = PackingLine::query()
                ->where('activo', true)
                ->find($filters['packing_line_id']);
        }
        $packaging = InventoryPackaging::query()
            ->where('activo', true)
            ->find($filters['packaging_id']);

        if (! $packaging) {
            return null;
        }

        $selectedLotIds = $filters['selected_lot_ids'] ?? [];

        $processLotModels = collect($processLots)->isNotEmpty()
            ? PackingProcessLot::query()
                ->whereIn('id', collect($processLots)->pluck('id')->all())
                ->orderBy('orden')
                ->orderBy('inicio_estimado')
                ->get(['id', 'setup_calibre', 'peso_neto'])
            : collect();

        if ($mode === 'lotes') {
            $selectedModels = $processLotModels->whereIn('id', $selectedLotIds);
            $kilos = max(0, (float) $selectedModels->sum('peso_neto'));
        } else {
            $kilos = max(0, (float) str_replace(',', '.', $filters['kilos'] ?? '0'));
        }

        if ($kilos <= 0) {
            return null;
        }

        $weightPerBox = max(0, (float) $packaging->peso_std);
        $boxesPerPallet = max(0, (float) $packaging->cantidad_cajas);
        $estimatedBoxes = $weightPerBox > 0 ? (int) ceil($kilos / $weightPerBox) : 0;
        $estimatedPallets = $boxesPerPallet > 0 ? (int) ceil($estimatedBoxes / $boxesPerPallet) : 0;

        $calibreCurve = $filters['calibre_curve'] ?? [];

        if ($mode === 'kilos' && ! empty($calibreCurve)) {
            $curveTotalPct = (float) collect($calibreCurve)->sum('percentage');
            $calibreDistribution = array_map(fn ($c) => [
                'calibre' => $c['calibre'],
                'percentage' => (float) $c['percentage'],
                'kilos' => $curveTotalPct > 0 ? round($kilos * ((float) $c['percentage'] / $curveTotalPct), 2) : 0,
            ], $calibreCurve);
        } else {
            $calibreDistribution = collect($processLots)
                ->when($mode === 'lotes', fn ($c) => $c->whereIn('id', $selectedLotIds))
                ->pluck('calibre_distribution')
                ->flatten(1)
                ->groupBy('calibre')
                ->map(fn ($group, $calibre) => [
                    'calibre' => $calibre,
                    'kilos' => round((float) $group->sum('kilos'), 2),
                ])
                ->values()
                ->all();

            $distTotalKilos = (float) collect($calibreDistribution)->sum('kilos');
            $calibreDistribution = array_map(fn ($d) => [
                'calibre' => $d['calibre'],
                'kilos' => $d['kilos'],
                'percentage' => $distTotalKilos > 0 ? round($d['kilos'] / $distTotalKilos * 100, 2) : 0,
            ], $calibreDistribution);
        }

        $lineCalibres = collect($calibreDistribution)->pluck('calibre')->filter()->map(fn ($c) => trim((string) $c))->unique()->values()->all();
        if (empty($lineCalibres)) {
            $filteredForCalibres = $selectedLotIds === []
                ? $processLotModels
                : $processLotModels->whereIn('id', $selectedLotIds);
            $lineCalibres = $filteredForCalibres->pluck('setup_calibre')->filter()->unique()->values()->all();
        }

        $boxesByCalibre = [];
        $palletsByCalibre = [];
        foreach ($calibreDistribution as $dist) {
            $calibreKey = trim((string) $dist['calibre']);
            $calibreKilos = $dist['kilos'];
            $calibreBoxes = $weightPerBox > 0 ? (int) ceil($calibreKilos / $weightPerBox) : 0;
            $boxesByCalibre[$calibreKey] = $calibreBoxes;
            $palletsByCalibre[$calibreKey] = $boxesPerPallet > 0 ? (int) ceil($calibreBoxes / $boxesPerPallet) : 0;
        }

        $preview = $consumptionService->preview(
            (int) $packaging->id,
            now()->toDateString(),
            (float) $estimatedBoxes,
            (float) $estimatedPallets,
            null,
            $lineCalibres,
            $boxesByCalibre ?: null,
            $palletsByCalibre ?: null
        );

        $materialIds = collect($preview['rows'])
            ->pluck('material_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $materialTypes = InventoryMaterial::query()
            ->whereIn('id', $materialIds)
            ->pluck('tipo_material', 'id');

        $materialRows = collect($preview['rows'])
            ->flatMap(function (array $row) use ($materialTypes, $calibreDistribution): array {
                $availableTotal = collect($row['stocks'] ?? [])->sum('stock');
                $rowCalibres = $row['calibres'] ?? null;
                $row['tipo_material'] = $materialTypes[(int) $row['material_id']] ?? null;
                $row['available_total'] = round((float) $availableTotal, 4);
                $row['shortage'] = round(max(0, (float) ($row['theoretical_total'] ?? 0) - (float) $availableTotal), 4);

                if (empty($rowCalibres) || empty($calibreDistribution)) {
                    $row['calibre'] = null;
                    return [$row];
                }

                $matching = collect($calibreDistribution)
                    ->filter(fn ($d) => in_array($d['calibre'], $rowCalibres, true))
                    ->values();

                if ($matching->isEmpty()) {
                    $row['calibre'] = null;
                    return [$row];
                }

                $totalPct = (float) $matching->sum('percentage');
                if ($totalPct <= 0) {
                    $row['calibre'] = null;
                    return [$row];
                }

                $totalRequired = (float) ($row['theoretical_total'] ?? 0);
                $totalAvailable = (float) ($row['available_total'] ?? 0);

                return $matching->map(function ($dist) use ($row, $totalRequired, $totalAvailable, $totalPct) {
                    $ratio = (float) $dist['percentage'] / $totalPct;
                    $required = round($totalRequired * $ratio, 4);
                    $available = round($totalAvailable * $ratio, 4);
                    return array_merge($row, [
                        'calibre' => $dist['calibre'],
                        'calibre_percentage' => $dist['percentage'],
                        'theoretical_total' => $required,
                        'available_total' => $available,
                        'shortage' => round(max(0, $required - $available), 4),
                    ]);
                })->all();
            })
            ->values()
            ->all();

        $materialRows = $this->expandSemiFinished($materialRows, $consumptionService);

        $semiFinished = $this->semiFinishedAvailability();

        return [
            'line' => $line ? [
                'id' => $line->id,
                'nombre' => $line->nombre,
                'tipo' => $line->tipo?->value ?? (string) $line->tipo,
            ] : null,
            'process_lots' => $processLots,
            'line_calibres' => $lineCalibres,
            'calibre_distribution' => $calibreDistribution,
            'mode' => $mode,
            'selected_lot_ids' => $selectedLotIds,
            'packaging' => [
                'id' => $packaging->id,
                'codigo' => $packaging->codigo,
                'nombre' => $packaging->nombre,
                'peso_std' => $weightPerBox,
                'cantidad_cajas' => $boxesPerPallet,
            ],
            'kilos' => round($kilos, 4),
            'estimated_boxes' => $estimatedBoxes,
            'estimated_pallets' => $estimatedPallets,
            'sheet' => $preview['sheet'],
            'materials' => $materialRows,
            'semi_finished' => $semiFinished,
            'calibre_curve' => $mode === 'kilos' ? $calibreCurve : [],
            'summary' => [
                'required_total' => round((float) collect($materialRows)->sum('theoretical_total'), 4),
                'available_total' => round((float) collect($materialRows)->sum('available_total'), 4),
                'shortage_count' => collect($materialRows)->where('shortage', '>', 0)->count(),
                'semi_finished_total' => round((float) collect($semiFinished['materials'])->sum('available_total'), 4),
            ],
        ];
    }

    private function expandSemiFinished(array $materialRows, TheoreticalConsumptionService $consumptionService): array
    {
        $expanded = [];
        foreach ($materialRows as $row) {
            $expanded[] = $row;

            if (($row['tipo_material'] ?? null) !== 'semielaborado') {
                continue;
            }
            if (($row['shortage'] ?? 0) <= 0) {
                continue;
            }

            $semiRequired = (float) ($row['theoretical_total'] ?? 0);
            if ($semiRequired <= 0) {
                continue;
            }

            $sheet = InventoryTechnicalSheet::query()
                ->where('material_id', (int) $row['material_id'])
                ->where('es_semielaborado', true)
                ->where('activo', true)
                ->where('fecha_vigencia_desde', '<=', now()->toDateString())
                ->where(function ($q) {
                    $q->whereNull('fecha_vigencia_hasta')
                      ->orWhere('fecha_vigencia_hasta', '>=', now()->toDateString());
                })
                ->orderByDesc('version')
                ->with(['unitItems.material.unit', 'unitItems.material.service', 'palletItems.material.unit', 'palletItems.material.service'])
                ->first();

            if (! $sheet) {
                continue;
            }

            $componentIds = collect($sheet->unitItems)
                ->merge($sheet->palletItems)
                ->pluck('material_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $componentStocks = $consumptionService->loadStockByLocation($componentIds);

            $components = [];

            foreach ($sheet->unitItems as $item) {
                $materialId = (int) $item->material_id;
                $required = (float) $item->cantidad_estandar * $semiRequired;
                $mat = $item->material;
                $available = (float) collect(($componentStocks[$materialId] ?? []))->sum('stock');
                $components[] = [
                    'es_despiece' => true,
                    'parent_material_id' => (int) $row['material_id'],
                    'material_id' => $materialId,
                    'material_codigo' => $mat?->codigo,
                    'material_nombre' => $mat?->nombre,
                    'unidad_medida' => $mat?->unit?->codigo,
                    'tipo_material' => $mat?->tipo_material ?? 'consumo',
                    'service_id' => $mat?->service_id,
                    'service_name' => $mat?->service?->name,
                    'calibre' => null,
                    'calibre_percentage' => null,
                    'semi_label' => true,
                    'theoretical_unit' => $required,
                    'theoretical_pallet' => 0,
                    'theoretical_total' => round($required, 4),
                    'available_total' => round($available, 4),
                    'shortage' => round(max(0, $required - $available), 4),
                    'stocks' => $componentStocks[$materialId] ?? [],
                    'real_total' => 0,
                    'waste_total' => 0,
                    'deviation_total' => 0,
                    'calibres' => null,
                ];
            }

            foreach ($sheet->palletItems as $item) {
                $materialId = (int) $item->material_id;
                $required = (float) $item->cantidad_estandar * $semiRequired;
                $mat = $item->material;

                $existing = null;
                foreach ($components as $k => $c) {
                    if ($c['material_id'] === $materialId) {
                        $existing = $k;
                        break;
                    }
                }

                if ($existing !== null) {
                    $components[$existing]['theoretical_pallet'] += $required;
                    $components[$existing]['theoretical_total'] += $required;
                    $newRequired = $components[$existing]['theoretical_total'];
                    $newAvailable = $components[$existing]['available_total'];
                    $components[$existing]['shortage'] = round(max(0, $newRequired - $newAvailable), 4);
                } else {
                    $available = (float) collect(($componentStocks[$materialId] ?? []))->sum('stock');
                    $components[] = [
                        'es_despiece' => true,
                        'parent_material_id' => (int) $row['material_id'],
                        'material_id' => $materialId,
                        'material_codigo' => $mat?->codigo,
                        'material_nombre' => $mat?->nombre,
                        'unidad_medida' => $mat?->unit?->codigo,
                        'tipo_material' => $mat?->tipo_material ?? 'consumo',
                        'service_id' => $mat?->service_id,
                        'service_name' => $mat?->service?->name,
                        'calibre' => null,
                        'calibre_percentage' => null,
                        'semi_label' => true,
                        'theoretical_unit' => 0,
                        'theoretical_pallet' => round($required, 4),
                        'theoretical_total' => round($required, 4),
                        'available_total' => round($available, 4),
                        'shortage' => round(max(0, $required - $available), 4),
                        'stocks' => $componentStocks[$materialId] ?? [],
                        'real_total' => 0,
                        'waste_total' => 0,
                        'deviation_total' => 0,
                        'calibres' => null,
                    ];
                }
            }

            $expanded = array_merge($expanded, $components);
        }

        return $expanded;
    }

    private function semiFinishedAvailability(): array
    {
        $summaryRows = DB::table('inventory_logistic_units as lu')
            ->join('inventory_materials as m', 'm.id', '=', 'lu.material_id')
            ->leftJoin('inventory_units as u', 'u.id', '=', 'm.unit_id')
            ->where('lu.status', 'active')
            ->where('lu.available_quantity', '>', 0)
            ->where('m.tipo_material', 'semielaborado')
            ->groupBy('m.id', 'm.codigo', 'm.nombre', 'u.codigo')
            ->orderBy('m.codigo')
            ->get([
                'm.id as material_id',
                'm.codigo as material_codigo',
                'm.nombre as material_nombre',
                'u.codigo as unidad_medida',
                DB::raw('SUM(lu.available_quantity) as available_total'),
                DB::raw('COUNT(lu.id) as lpn_count'),
            ]);

        $lpnRows = DB::table('inventory_logistic_units as lu')
            ->join('inventory_materials as m', 'm.id', '=', 'lu.material_id')
            ->leftJoin('inventory_units as u', 'u.id', '=', 'm.unit_id')
            ->leftJoin('inventory_locations as l', 'l.id', '=', 'lu.current_location_id')
            ->where('lu.status', 'active')
            ->where('lu.available_quantity', '>', 0)
            ->where('m.tipo_material', 'semielaborado')
            ->orderBy('m.codigo')
            ->orderBy('l.nombre')
            ->orderBy('lu.license_plate_number')
            ->limit(200)
            ->get([
                'lu.id',
                'lu.license_plate_number',
                'lu.available_quantity',
                'lu.lot_code',
                'lu.spatial_prefix',
                'lu.spatial_column',
                'lu.spatial_row',
                'm.codigo as material_codigo',
                'm.nombre as material_nombre',
                'u.codigo as unidad_medida',
                'l.codigo as location_code',
                'l.nombre as location_name',
            ]);

        return [
            'materials' => $summaryRows
                ->map(fn ($row) => [
                    'material_id' => (int) $row->material_id,
                    'material_codigo' => $row->material_codigo,
                    'material_nombre' => $row->material_nombre,
                    'unidad_medida' => $row->unidad_medida,
                    'available_total' => round((float) $row->available_total, 4),
                    'lpn_count' => (int) $row->lpn_count,
                ])
                ->all(),
            'lpns' => $lpnRows
                ->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'lpn' => $row->license_plate_number,
                    'material_codigo' => $row->material_codigo,
                    'material_nombre' => $row->material_nombre,
                    'unidad_medida' => $row->unidad_medida,
                    'available_quantity' => round((float) $row->available_quantity, 4),
                    'location' => $this->locationLabel($row->location_code, $row->location_name),
                    'spatial_position' => $this->spatialPositionLabel($row->spatial_prefix, $row->spatial_column, $row->spatial_row),
                    'lot_code' => $row->lot_code,
                ])
                ->all(),
            'lpn_limit' => 200,
        ];
    }

    private function locationLabel(?string $code, ?string $name): string
    {
        return trim(implode(' · ', array_filter([
            $code,
            $name,
        ])));
    }

    private function spatialPositionLabel(?string $prefix, ?string $column, ?string $row): ?string
    {
        $parts = array_filter([
            $prefix ? 'Prefijo '.$prefix : null,
            $column ? 'Columna '.$column : null,
            $row ? 'Fila '.$row : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
