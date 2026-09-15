<?php

namespace App\Services\Inventory;

use App\Models\InventoryLedgerEvent;
use App\Models\InventoryLocation;
use App\Models\InventoryStockPosition;
use App\Models\InventoryStockLocation;
use App\Models\InventoryTransferUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StockProjectionService
{
    public function applyDelta(int $materialId, int $locationId, float $delta, ?int $ledgerEventId = null, bool $allowNegativeStock = false): InventoryStockLocation
    {
        $location = InventoryLocation::query()->findOrFail($locationId);
        $stock = InventoryStockLocation::query()
            ->where('material_id', $materialId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = InventoryStockLocation::create([
                'material_id' => $materialId,
                'location_id' => $locationId,
                'stock_actual' => 0,
            ]);
        }

        $nextStock = (float) $stock->stock_actual + $delta;

        if (! $location->permite_stock_negativo && ! $allowNegativeStock && $nextStock < 0) {
            throw ValidationException::withMessages([
                'stock' => "Stock insuficiente en {$location->nombre}. Disponible: {$stock->stock_actual}. Delta: {$delta}.",
            ]);
        }

        $stock->forceFill([
            'stock_actual' => $nextStock,
            'last_ledger_event_id' => $ledgerEventId,
            'last_rebuilt_at' => now(),
        ])->save();

        return $stock;
    }

    public function rebuildAll(): void
    {
        DB::transaction(function (): void {
            InventoryStockLocation::query()->delete();

            $rows = InventoryLedgerEvent::query()
                ->selectRaw('material_id, location_id, SUM(signed_quantity) as total')
                ->whereNotNull('material_id')
                ->whereNotNull('location_id')
                ->whereIn('stock_effect', ['in', 'out'])
                ->groupBy('material_id', 'location_id')
                ->get();

            foreach ($rows as $row) {
                InventoryStockLocation::create([
                    'material_id' => $row->material_id,
                    'location_id' => $row->location_id,
                    'stock_actual' => (float) $row->total,
                    'last_rebuilt_at' => now(),
                ]);
            }
        });
    }

    public function rebuildMaterialLocation(int $materialId, int $locationId): void
    {
        $total = (float) InventoryLedgerEvent::query()
            ->where('material_id', $materialId)
            ->where('location_id', $locationId)
            ->whereIn('stock_effect', ['in', 'out'])
            ->sum('signed_quantity');

        InventoryStockLocation::query()->updateOrCreate(
            ['material_id' => $materialId, 'location_id' => $locationId],
            ['stock_actual' => $total, 'last_rebuilt_at' => now()]
        );
    }

    public function syncMaterialLocationFromPositions(int $materialId, int $locationId): InventoryStockLocation
    {
        $total = $this->inTransitAdjustedPositionTotal($materialId, $locationId);

        return InventoryStockLocation::query()->updateOrCreate(
            ['material_id' => $materialId, 'location_id' => $locationId],
            [
                'stock_actual' => round(max($total, 0), 4),
                'last_rebuilt_at' => now(),
            ]
        );
    }

    public function syncAllFromPositions(): void
    {
        if (! $this->positionsTableExists()) {
            return;
        }

        DB::transaction(function (): void {
            $totals = InventoryStockPosition::query()
                ->selectRaw('material_id, location_id, SUM(quantity) as total')
                ->groupBy('material_id', 'location_id')
                ->get();

            $inTransit = InventoryTransferUnit::query()
                ->selectRaw('material_id, origin_location_id, SUM(quantity) as total')
                ->where('status', 'in_transit')
                ->groupBy('material_id', 'origin_location_id')
                ->get()
                ->keyBy(fn ($row) => (int) $row->material_id.'-'.(int) $row->origin_location_id);

            $activePairs = [];

            foreach ($totals as $row) {
                $key = (int) $row->material_id.'-'.(int) $row->location_id;
                $inTransitTotal = (float) ($inTransit->get($key)?->total ?? 0);

                $activePairs[] = [
                    'material_id' => (int) $row->material_id,
                    'location_id' => (int) $row->location_id,
                ];

                InventoryStockLocation::query()->updateOrCreate(
                    [
                        'material_id' => $row->material_id,
                        'location_id' => $row->location_id,
                    ],
                    [
                        'stock_actual' => round(max((float) $row->total - $inTransitTotal, 0), 4),
                        'last_rebuilt_at' => now(),
                    ]
                );
            }

            $existingRows = InventoryStockLocation::query()->get(['id', 'material_id', 'location_id']);

            foreach ($existingRows as $stockRow) {
                $stillActive = collect($activePairs)->contains(
                    fn (array $pair) => $pair['material_id'] === (int) $stockRow->material_id
                        && $pair['location_id'] === (int) $stockRow->location_id
                );

                if (! $stillActive) {
                    $stockRow->forceFill([
                        'stock_actual' => 0,
                        'last_rebuilt_at' => now(),
                    ])->save();
                }
            }
        });
    }

    private function inTransitAdjustedPositionTotal(int $materialId, int $locationId): float
    {
        $total = $this->positionsTableExists()
            ? (float) InventoryStockPosition::query()
                ->where('material_id', $materialId)
                ->where('location_id', $locationId)
                ->sum('quantity')
            : 0.0;

        $inTransit = (float) InventoryTransferUnit::query()
            ->where('material_id', $materialId)
            ->where('origin_location_id', $locationId)
            ->where('status', 'in_transit')
            ->sum('quantity');

        return round($total - $inTransit, 4);
    }

    private function positionsTableExists(): bool
    {
        return Schema::hasTable('inventory_stock_positions');
    }
}
