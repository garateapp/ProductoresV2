<?php

namespace App\Console\Commands;

use App\Models\InventoryLogisticUnit;
use App\Models\InventoryStockLocation;
use App\Models\InventoryStockPosition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BootstrapInventoryStockPositionsCommand extends Command
{
    protected $signature = 'inventory:bootstrap-stock-positions {--dry-run}';

    protected $description = 'Pobla posiciones de stock desde el stock actual y las unidades logisticas activas';

    public function handle(): int
    {
        $stockRows = InventoryStockLocation::query()
            ->with(['location:id,codigo,nombre', 'material:id,codigo,nombre'])
            ->where('stock_actual', '>', 0)
            ->orderBy('location_id')
            ->orderBy('material_id')
            ->get();

        if ($stockRows->isEmpty()) {
            $this->info('No hay stock actual para bootstrapear posiciones.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $unresolvedRows = 0;

        if ($this->option('dry-run')) {
            $this->info(sprintf('Se detectaron %d filas de stock para bootstrap.', $stockRows->count()));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($stockRows, &$created, &$updated, &$unresolvedRows): void {
            foreach ($stockRows as $stockRow) {
                $remainingQuantity = round((float) $stockRow->stock_actual, 4);
                $allocatedQuantity = 0.0;

                $logisticUnits = InventoryLogisticUnit::query()
                    ->where('material_id', $stockRow->material_id)
                    ->where('current_location_id', $stockRow->location_id)
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->get(['id', 'available_quantity', 'lot_code']);

                foreach ($logisticUnits as $logisticUnit) {
                    if ($remainingQuantity <= 0) {
                        break;
                    }

                    $positionQuantity = round(min((float) $logisticUnit->available_quantity, $remainingQuantity), 4);

                    if ($positionQuantity <= 0) {
                        continue;
                    }

                    $position = InventoryStockPosition::updateOrCreate(
                        [
                            'material_id' => $stockRow->material_id,
                            'location_id' => $stockRow->location_id,
                            'logistic_unit_id' => $logisticUnit->id,
                            'lot_code' => $logisticUnit->normalizedLotCode(),
                            'status' => 'available',
                        ],
                        [
                            'quantity' => $positionQuantity,
                            'metadata' => [
                                'bootstrap_source' => 'inventory_stock_locations',
                                'inventory_stock_location_id' => $stockRow->id,
                                'material_code' => $stockRow->material?->codigo,
                                'location_code' => $stockRow->location?->codigo,
                                'logistic_unit_code' => $logisticUnit->license_plate_number,
                            ],
                        ]
                    );

                    if ($position->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    $allocatedQuantity = round($allocatedQuantity + $positionQuantity, 4);
                    $remainingQuantity = round((float) $stockRow->stock_actual - $allocatedQuantity, 4);
                }

                if ($remainingQuantity > 0) {
                    $position = InventoryStockPosition::updateOrCreate(
                        [
                            'material_id' => $stockRow->material_id,
                            'location_id' => $stockRow->location_id,
                            'logistic_unit_id' => null,
                            'lot_code' => null,
                            'status' => 'available',
                        ],
                        [
                            'quantity' => $remainingQuantity,
                            'metadata' => [
                                'bootstrap_source' => 'inventory_stock_locations',
                                'inventory_stock_location_id' => $stockRow->id,
                                'material_code' => $stockRow->material?->codigo,
                                'location_code' => $stockRow->location?->codigo,
                                'resolved_logistic_units' => $logisticUnits->count(),
                            ],
                        ]
                    );

                    if ($position->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    $unresolvedRows++;

                    Log::warning('inventory.bootstrap_stock_positions.unresolved_row', [
                        'inventory_stock_location_id' => $stockRow->id,
                        'material_id' => $stockRow->material_id,
                        'location_id' => $stockRow->location_id,
                        'quantity' => (float) $remainingQuantity,
                        'resolved_logistic_units' => $logisticUnits->count(),
                    ]);
                }
            }
        });

        $this->info(sprintf('Posiciones bootstrap: %d creadas, %d actualizadas.', $created, $updated));

        if ($unresolvedRows > 0) {
            $this->warn(sprintf('Filas sin pallet resuelto: %d', $unresolvedRows));
        }

        return self::SUCCESS;
    }
}
