<?php

namespace App\Console\Commands;

use App\Models\InventoryLocation;
use App\Models\InventoryStockLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateInventoryPilotCandidatesCommand extends Command
{
    protected $signature = 'inventory:pilot-generate-candidates
                            {--location-code=BODEGA_CENTRAL}
                            {--limit=20}
                            {--path=}';

    protected $description = 'Genera un CSV candidato para alta inicial de pallets/LPN del piloto';

    public function handle(): int
    {
        $locationCode = (string) $this->option('location-code');
        $limit = max(1, (int) $this->option('limit'));
        $relativePath = $this->option('path') ?: 'inventory/pilot-candidates-'.Str::slug($locationCode).'-'.now()->format('Ymd_His').'.csv';
        $absolutePath = Storage::disk('local')->path($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $location = InventoryLocation::query()->where('codigo', $locationCode)->first();
        if (! $location) {
            $this->error('No existe la ubicación indicada.');
            return self::FAILURE;
        }

        $rows = InventoryStockLocation::query()
            ->with(['material:id,codigo,nombre,unit_id', 'material.unit:id,codigo'])
            ->where('location_id', $location->id)
            ->where('stock_actual', '>', 0)
            ->orderByDesc('stock_actual')
            ->limit($limit)
            ->get();

        $handle = fopen($absolutePath, 'wb');
        if (! $handle) {
            $this->error('No fue posible crear el archivo candidato.');
            return self::FAILURE;
        }

        fputcsv($handle, [
            'license_plate_number',
            'material_id',
            'current_location_id',
            'base_quantity',
            'available_quantity',
            'unit_id',
            'lot_code',
            'supplier_lot',
            'production_batch',
            'reference_type',
            'reference_id',
            'received_at',
            'status',
            'material_codigo',
            'material_nombre',
            'unidad_codigo',
        ], ';');

        $index = 1;
        foreach ($rows as $row) {
            fputcsv($handle, [
                sprintf('LPN-%s-%s-%03d', $location->codigo, $row->material?->codigo, $index),
                $row->material_id,
                $location->id,
                (float) $row->stock_actual,
                (float) $row->stock_actual,
                $row->material?->unit_id,
                'PILOTO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                null,
                'PILOTO-SEMANA-1',
                'pilot',
                $index,
                now()->format('Y-m-d H:i:s'),
                'active',
                $row->material?->codigo,
                $row->material?->nombre,
                $row->material?->unit?->codigo,
            ], ';');
            $index++;
        }

        fclose($handle);

        $this->info('Archivo de candidatos generado.');
        $this->line('Ubicación: '.$location->codigo);
        $this->line('Filas: '.$rows->count());
        $this->line('Archivo: '.$absolutePath);

        return self::SUCCESS;
    }
}
