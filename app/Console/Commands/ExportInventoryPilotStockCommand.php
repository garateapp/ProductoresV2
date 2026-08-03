<?php

namespace App\Console\Commands;

use App\Models\InventoryStockLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportInventoryPilotStockCommand extends Command
{
    protected $signature = 'inventory:pilot-export-stock {--path=}';

    protected $description = 'Exporta stock positivo por ubicación para preparar el piloto de pallets/LPN';

    public function handle(): int
    {
        $relativePath = $this->option('path') ?: 'inventory/pilot-stock-'.now()->format('Ymd_His').'.csv';
        $absolutePath = Storage::disk('local')->path($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $handle = fopen($absolutePath, 'wb');

        if (! $handle) {
            $this->error('No fue posible crear el archivo CSV.');
            return self::FAILURE;
        }

        fputcsv($handle, [
            'material_id',
            'material_codigo',
            'material_nombre',
            'location_id',
            'location_codigo',
            'location_nombre',
            'stock_actual',
            'unidad',
            'suggested_lpn_prefix',
            'suggested_label',
        ], ';');

        $rows = InventoryStockLocation::query()
            ->with(['material:id,codigo,nombre,unit_id', 'material.unit:id,codigo', 'location:id,codigo,nombre'])
            ->where('stock_actual', '>', 0)
            ->orderBy('location_id')
            ->orderBy('material_id')
            ->get();

        foreach ($rows as $row) {
            $materialCode = (string) ($row->material?->codigo ?? 'MAT');
            $locationCode = (string) ($row->location?->codigo ?? 'LOC');
            fputcsv($handle, [
                $row->material_id,
                $materialCode,
                $row->material?->nombre,
                $row->location_id,
                $locationCode,
                $row->location?->nombre,
                (float) $row->stock_actual,
                $row->material?->unit?->codigo,
                'LPN-'.$locationCode.'-'.$materialCode,
                $locationCode.' / '.$materialCode,
            ], ';');
        }

        fclose($handle);

        $this->info('CSV exportado correctamente.');
        $this->line('Archivo: '.$absolutePath);
        $this->line('Filas: '.$rows->count());

        return self::SUCCESS;
    }
}
