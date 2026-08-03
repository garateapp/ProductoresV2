<?php

namespace App\Services\Inventory;

use App\Models\InventoryProduction;
use App\Models\InventoryTechnicalSheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
class TheoreticalConsumptionService
{
    public function forProduction(InventoryProduction $production): array
    {
        return $this->preview(
            (int) $production->packaging_id,
            (string) $production->fecha?->format('Y-m-d'),
            (float) $production->cantidad_cajas,
            (float) $production->cantidad_pallets,
            $production->id
        );
    }

    public function preview(int $packagingId, string $date, float $boxes, float $pallets, ?int $productionId = null, ?array $calibres = null, ?array $boxesByCalibre = null, ?array $palletsByCalibre = null): array
    {
        $sheet = InventoryTechnicalSheet::query()
            ->with(['unitItems.material.unit', 'unitItems.material.service', 'palletItems.material.unit', 'palletItems.material.service', 'packaging'])
            ->where('packaging_id', $packagingId)
            ->where('activo', true)
            ->where('fecha_vigencia_desde', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('fecha_vigencia_hasta')
                    ->orWhere('fecha_vigencia_hasta', '>=', $date);
            })
            ->orderByDesc('version')
            ->first();

        if (! $sheet) {
            $allActive = InventoryTechnicalSheet::query()
                ->where('packaging_id', $packagingId)
                ->where('activo', true)
                ->get(['id', 'version', 'fecha_vigencia_desde', 'fecha_vigencia_hasta', 'activo', 'packaging_id'])
                ->toArray();
            Log::warning('Technical sheet not found for packaging', [
                'packaging_id' => $packagingId,
                'date' => $date,
                'all_active_for_packaging' => $allActive,
            ]);
            return [
                'sheet' => null,
                'rows' => [],
                'summary' => [
                    'theoretical_total' => 0,
                    'real_total' => 0,
                    'waste_total' => 0,
                    'deviation_total' => 0,
                ],
            ];
        }

        $rows = [];
        foreach ($sheet->unitItems as $item) {
            if (! $this->matchesCalibre($item, $calibres)) {
                continue;
            }
            $materialId = (int) $item->material_id;
            if (! isset($rows[$materialId])) {
                $rows[$materialId] = $this->baseRow($item->material);
            }
            $itemCalibre = ! empty($item->calibre) ? trim($item->calibre) : null;
            $effectiveBoxes = ($itemCalibre && $boxesByCalibre && isset($boxesByCalibre[$itemCalibre]))
                ? (float) $boxesByCalibre[$itemCalibre]
                : $boxes;
            $rows[$materialId]['theoretical_unit'] += $effectiveBoxes * (float) $item->cantidad_estandar;
            $rows[$materialId]['theoretical_total'] += $effectiveBoxes * (float) $item->cantidad_estandar;
            $rows[$materialId]['calibres'][] = $item->calibre;
        }

        foreach ($sheet->palletItems as $item) {
            if (! $this->matchesCalibre($item, $calibres)) {
                continue;
            }
            $materialId = (int) $item->material_id;
            if (! isset($rows[$materialId])) {
                $rows[$materialId] = $this->baseRow($item->material);
            }
            $itemCalibre = ! empty($item->calibre) ? trim($item->calibre) : null;
            $effectivePallets = ($itemCalibre && $palletsByCalibre && isset($palletsByCalibre[$itemCalibre]))
                ? (float) $palletsByCalibre[$itemCalibre]
                : $pallets;
            $rows[$materialId]['theoretical_pallet'] += $effectivePallets * (float) $item->cantidad_estandar;
            $rows[$materialId]['theoretical_total'] += $effectivePallets * (float) $item->cantidad_estandar;
            $rows[$materialId]['calibres'][] = $item->calibre;
        }

        $realConsumption = $productionId ? $this->loadRealConsumption($productionId) : collect();
        $wasteConsumption = $productionId ? $this->loadWasteConsumption($productionId) : collect();

        $materialIds = array_keys($rows);
        $stockData = $this->loadStockByLocation($materialIds);

        $rows = collect($rows)->map(function (array $row, int $materialId) use ($realConsumption, $wasteConsumption, $stockData) {
            $real = (float) ($realConsumption[$materialId] ?? 0);
            $waste = (float) ($wasteConsumption[$materialId] ?? 0);
            $row['real_total'] = $real;
            $row['waste_total'] = $waste;
            $row['deviation_total'] = $real - $row['theoretical_total'];
            $row['stocks'] = $stockData[$materialId] ?? [];
            $calibres = array_unique(array_filter($row['calibres']));
            $row['calibres'] = $calibres === [] ? null : array_values($calibres);

            return $row;
        })->sortBy('material_codigo')->values();

        return [
            'sheet' => [
                'id' => $sheet->id,
                'version' => $sheet->version,
                'packaging' => $sheet->packaging?->nombre,
                'vigencia' => [
                    'desde' => optional($sheet->fecha_vigencia_desde)->format('Y-m-d'),
                    'hasta' => optional($sheet->fecha_vigencia_hasta)->format('Y-m-d'),
                ],
            ],
            'rows' => $rows->all(),
            'summary' => [
                'theoretical_total' => round((float) $rows->sum('theoretical_total'), 4),
                'real_total' => round((float) $rows->sum('real_total'), 4),
                'waste_total' => round((float) $rows->sum('waste_total'), 4),
                'deviation_total' => round((float) $rows->sum('deviation_total'), 4),
            ],
        ];
    }

    public function loadStockByLocation(array $materialIds): Collection
    {
        if ($materialIds === [] || ! Schema::hasTable('inventory_locations')) {
            return collect();
        }

        $positionsByMaterialLocation = $this->loadStockPositionsByLocation($materialIds);
        $locationRows = collect();

        if (Schema::hasTable('inventory_stock_locations')) {
            $locationRows = DB::table('inventory_stock_locations as s')
                ->join('inventory_locations as l', 'l.id', '=', 's.location_id')
                ->whereIn('s.material_id', $materialIds)
                ->where('s.stock_actual', '>', 0)
                ->where('l.activo', true)
                ->select([
                    's.material_id',
                    's.location_id',
                    'l.codigo as location_code',
                    'l.nombre as location_name',
                    's.stock_actual',
                ])
                ->orderBy('l.nombre')
                ->get();
        }

        $indexedRows = $locationRows
            ->mapWithKeys(fn ($item) => [
                $this->stockLocationKey((int) $item->material_id, (int) $item->location_id) => $item,
            ]);

        $positionsByMaterialLocation->each(function (Collection $locationGroups, int $materialId) use (&$indexedRows): void {
            $locationGroups->each(function (Collection $positions, int $locationId) use (&$indexedRows, $materialId): void {
                $key = $this->stockLocationKey($materialId, $locationId);

                if ($indexedRows->has($key)) {
                    return;
                }

                $firstPosition = $positions->first();
                $indexedRows->put($key, (object) [
                    'material_id' => $materialId,
                    'location_id' => $locationId,
                    'location_code' => data_get($firstPosition, 'location.codigo'),
                    'location_name' => data_get($firstPosition, 'location.nombre'),
                    'stock_actual' => $positions->sum('quantity'),
                ]);
            });
        });

        return $indexedRows
            ->values()
            ->sortBy('location_name')
            ->groupBy('material_id')
            ->map(function (Collection $items) use ($positionsByMaterialLocation): array {
                return $items->map(function ($item) use ($positionsByMaterialLocation): array {
                    $materialId = (int) $item->material_id;
                    $locationId = (int) $item->location_id;
                    $positions = $positionsByMaterialLocation
                        ->get($materialId, collect())
                        ->get($locationId, collect())
                        ->values()
                        ->all();

                    return [
                        'location_id' => $locationId,
                        'location_code' => $item->location_code,
                        'location' => $this->formatLocationLabel($item->location_code, $item->location_name),
                        'stock' => round((float) $item->stock_actual, 4),
                        'positions' => $positions,
                    ];
                })->values()->all();
            });
    }

    private function loadStockPositionsByLocation(array $materialIds): Collection
    {
        if (
            $materialIds === []
            || ! Schema::hasTable('inventory_stock_positions')
            || ! Schema::hasTable('inventory_locations')
        ) {
            return collect();
        }

        $query = DB::table('inventory_stock_positions as p')
            ->join('inventory_locations as l', 'l.id', '=', 'p.location_id')
            ->whereIn('p.material_id', $materialIds)
            ->where('p.quantity', '>', 0)
            ->where('l.activo', true)
            ->select([
                'p.id',
                'p.material_id',
                'p.location_id',
                'p.logistic_unit_id',
                'p.quantity',
                'p.lot_code',
                'p.status',
                'l.codigo as location_code',
                'l.nombre as location_name',
            ]);

        if (Schema::hasTable('inventory_logistic_units')) {
            $query
                ->leftJoin('inventory_logistic_units as lu', 'lu.id', '=', 'p.logistic_unit_id')
                ->addSelect('lu.license_plate_number');
        }

        return $query
            ->orderBy('l.nombre')
            ->orderByDesc('p.quantity')
            ->orderBy('p.id')
            ->get()
            ->map(fn ($position) => [
                'id' => (int) $position->id,
                'material_id' => (int) $position->material_id,
                'quantity' => round((float) $position->quantity, 4),
                'lot_code' => $position->lot_code,
                'status' => $position->status,
                'location' => [
                    'id' => (int) $position->location_id,
                    'codigo' => $position->location_code,
                    'nombre' => $position->location_name,
                ],
                'logistic_unit' => $position->logistic_unit_id ? [
                    'id' => (int) $position->logistic_unit_id,
                    'license_plate_number' => $position->license_plate_number ?? null,
                ] : null,
            ])
            ->groupBy([
                fn (array $position) => (int) $position['material_id'],
                fn (array $position) => (int) data_get($position, 'location.id'),
            ]);
    }

    private function stockLocationKey(int $materialId, int $locationId): string
    {
        return $materialId.'-'.$locationId;
    }

    private function formatLocationLabel(?string $code, ?string $name): string
    {
        return trim(implode(' · ', array_filter([$code, $name])));
    }

    private function matchesCalibre($item, ?array $calibres): bool
    {
        if ($calibres === null || $calibres === []) {
            return true;
        }

        $itemCalibre = ! empty($item->calibre) ? trim($item->calibre) : null;

        if ($itemCalibre === null) {
            return true;
        }

        $normalizedCalibres = array_map(fn ($c) => trim((string) $c), $calibres);

        return in_array($itemCalibre, $normalizedCalibres, true);
    }

    private function baseRow($material): array
    {
        return [
            'material_id' => $material?->id,
            'material_codigo' => $material?->codigo,
            'material_nombre' => $material?->nombre,
            'service_id' => $material?->service_id,
            'service_name' => $material?->service?->name,
            'unidad_medida' => $material?->unit?->codigo,
            'theoretical_unit' => 0.0,
            'theoretical_pallet' => 0.0,
            'theoretical_total' => 0.0,
            'real_total' => 0.0,
            'waste_total' => 0.0,
            'deviation_total' => 0.0,
            'calibres' => [],
        ];
    }

    private function loadRealConsumption(int $productionId): Collection
    {
        return DB::table('inventory_movement_details as d')
            ->join('inventory_movements as m', 'm.id', '=', 'd.movement_id')
            ->join('inventory_movement_types as t', 't.id', '=', 'm.movement_type_id')
            ->where('m.estado', '!=', 'borrador')
            ->where('m.referencia_tipo', 'production')
            ->where('m.referencia_id', $productionId)
            ->where('t.codigo', 'CONSUMO')
            ->groupBy('d.material_id')
            ->selectRaw('d.material_id as material_id, sum(d.cantidad) as total')
            ->pluck('total', 'material_id');
    }

    private function loadWasteConsumption(int $productionId): Collection
    {
        return DB::table('inventory_movement_details as d')
            ->join('inventory_movements as m', 'm.id', '=', 'd.movement_id')
            ->join('inventory_movement_types as t', 't.id', '=', 'm.movement_type_id')
            ->where('m.estado', '!=', 'borrador')
            ->where('m.referencia_tipo', 'production')
            ->where('m.referencia_id', $productionId)
            ->where('t.codigo', 'MERMA')
            ->groupBy('d.material_id')
            ->selectRaw('d.material_id as material_id, sum(d.cantidad) as total')
            ->pluck('total', 'material_id');
    }
}
