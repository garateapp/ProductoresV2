<?php

namespace App\Console\Commands;

use App\Models\InventoryStockPosition;
use App\Models\InventoryStockLocation;
use App\Models\InventoryTransferUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileInventoryStockCommand extends Command
{
    protected $signature = 'inventory:stock-reconcile
        {--dry-run : Muestra los cambios sin aplicarlos}
        {--aggressive : También ajusta a cero pares sin posiciones (legacy)}
        {--json : Salida JSON compacta}';

    protected $description = 'Reconstruye inventory_stock_locations a partir de inventory_stock_positions (fuente única de verdad)';

    public function handle(): int
    {
        if (! Schema::hasTable('inventory_stock_positions')) {
            $this->error('La tabla de posiciones no existe. Nada que reconciliar.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $aggressive = (bool) $this->option('aggressive');
        $json = (bool) $this->option('json');

        $changes = [];
        $legacyPairs = [];

        DB::transaction(function () use ($dryRun, $aggressive, &$changes, &$legacyPairs): void {
            $positionsByPair = InventoryStockPosition::query()
                ->selectRaw('material_id, location_id, SUM(quantity) as total')
                ->where('quantity', '>', 0)
                ->groupBy('material_id', 'location_id')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->material_id.'-'.$row->location_id => (float) $row->total])
                ->all();

            $inTransitByPair = InventoryTransferUnit::query()
                ->selectRaw('material_id, origin_location_id, SUM(quantity) as total')
                ->where('status', 'in_transit')
                ->groupBy('material_id', 'origin_location_id')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->material_id.'-'.$row->origin_location_id => (float) $row->total])
                ->all();

            $stockRows = InventoryStockLocation::query()
                ->get(['id', 'material_id', 'location_id', 'stock_actual'])
                ->keyBy(fn ($row) => $row->material_id.'-'.$row->location_id);

            $materials = DB::table('inventory_materials')->pluck('codigo', 'id');
            $locations = DB::table('inventory_locations')->pluck('codigo', 'id');

            foreach ($positionsByPair as $key => $positionsTotal) {
                $inTransitTotal = (float) ($inTransitByPair[$key] ?? 0);
                $expected = round(max($positionsTotal - $inTransitTotal, 0), 4);

                $currentRow = $stockRows->get($key);
                $current = (float) ($currentRow->stock_actual ?? 0);

                if (abs($current - $expected) > 1e-6) {
                    $changes[] = [
                        'material' => $materials->get((int) (explode('-', $key)[0]), explode('-', $key)[0]),
                        'material_id' => (int) explode('-', $key)[0],
                        'location' => $locations->get((int) (explode('-', $key)[1]), explode('-', $key)[1]),
                        'location_id' => (int) explode('-', $key)[1],
                        'from' => $current,
                        'to' => $expected,
                        'positions' => $positionsTotal,
                        'in_transit' => $inTransitTotal,
                        'reason' => 'ajuste_posiciones',
                    ];

                    if (! $dryRun) {
                        InventoryStockLocation::query()->updateOrCreate(
                            [
                                'material_id' => (int) explode('-', $key)[0],
                                'location_id' => (int) explode('-', $key)[1],
                            ],
                            [
                                'stock_actual' => $expected,
                                'last_rebuilt_at' => now(),
                            ]
                        );
                    }
                }
            }

            foreach ($stockRows as $key => $stockRow) {
                if (array_key_exists($key, $positionsByPair)) {
                    continue;
                }

                if ((float) $stockRow->stock_actual <= 0) {
                    continue;
                }

                $legacyPairs[] = [
                    'material' => $materials->get((int) $stockRow->material_id, (int) $stockRow->material_id),
                    'material_id' => (int) $stockRow->material_id,
                    'location' => $locations->get((int) $stockRow->location_id, (int) $stockRow->location_id),
                    'location_id' => (int) $stockRow->location_id,
                    'stock' => (float) $stockRow->stock_actual,
                    'in_transit' => (float) ($inTransitByPair[$key] ?? 0),
                    'reason' => 'legacy_sin_posiciones',
                ];

                if ($aggressive && ! $dryRun) {
                    InventoryStockLocation::query()->where('id', $stockRow->id)->update([
                        'stock_actual' => 0,
                        'last_rebuilt_at' => now(),
                    ]);
                }
            }
        });

        $this->report($changes, $legacyPairs, $dryRun, $aggressive, $json);

        return self::SUCCESS;
    }

    private function report(array $changes, array $legacyPairs, bool $dryRun, bool $aggressive, bool $json): void
    {
        $mode = $dryRun ? 'DRY-RUN (sin cambios)' : ($aggressive ? 'AGGRESSIVE (incluye legacy)' : 'SAFE (solo con posiciones)');

        if ($json) {
            $this->line(json_encode([
                'mode' => $mode,
                'changes' => $changes,
                'legacy' => $legacyPairs,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->info("Modo: {$mode}");

        if (empty($changes) && empty($legacyPairs)) {
            $this->line('Sin divergencias: stock consistente con posiciones.');

            return;
        }

        $this->line('');

        if (! empty($changes)) {
            $this->line('Ajustes por posiciones: '.count($changes));
            $this->table(
                ['material_id', 'material', 'location_id', 'location', 'desde', 'hacia', 'posiciones', 'en_transito'],
                array_map(fn (array $row) => [
                    $row['material_id'],
                    $row['material'],
                    $row['location_id'],
                    $row['location'],
                    rtrim(rtrim(number_format($row['from'], 4, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($row['to'], 4, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($row['positions'], 4, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($row['in_transit'], 4, '.', ''), '0'), '.'),
                ], $changes)
            );
        }

        if (! empty($legacyPairs)) {
            $this->line('');
            $this->warn('Pares sin posiciones con stock > 0 (se mantienen por defecto): '.count($legacyPairs));
            if ($aggressive) {
                $this->warn('--aggressive activo: se pondrán a cero en la próxima corrida.');
            }
            foreach ($legacyPairs as $row) {
                $this->line(sprintf(
                    '  - [legacy] material %s (%s) en ubicación %s (%s): %s unidades' . ($row['in_transit'] > 0 ? ' (en tránsito: '.$row['in_transit'].')' : ''),
                    $row['material_id'],
                    $row['material'],
                    $row['location_id'],
                    $row['location'],
                    rtrim(rtrim(number_format($row['stock'], 4, '.', ''), '0'), '.')
                ));
            }
        }
    }
}