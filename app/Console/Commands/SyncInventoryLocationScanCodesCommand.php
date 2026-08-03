<?php

namespace App\Console\Commands;

use App\Models\InventoryLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncInventoryLocationScanCodesCommand extends Command
{
    protected $signature = 'inventory:locations-sync-scan-codes {--prefix=LOC} {--dry-run}';

    protected $description = 'Normaliza scan_code y path_code de ubicaciones de inventario';

    public function handle(): int
    {
        $prefix = Str::upper((string) $this->option('prefix'));
        $locations = InventoryLocation::query()->orderBy('id')->get();

        $rows = [];

        foreach ($locations as $location) {
            $scanCode = $location->scan_code;
            if (! $scanCode || trim($scanCode) === '') {
                $scanCode = $prefix.'-'.Str::upper((string) $location->codigo);
            }

            $pathCode = $this->buildPathCode($location, $prefix);

            $rows[] = [
                'id' => $location->id,
                'codigo' => $location->codigo,
                'scan_code' => $scanCode,
                'path_code' => $pathCode,
            ];
        }

        $this->table(['ID', 'Código', 'Scan', 'Path'], array_slice($rows, 0, 20));
        $this->line('Ubicaciones procesadas: '.count($rows));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                InventoryLocation::query()
                    ->whereKey($row['id'])
                    ->update([
                        'scan_code' => $row['scan_code'],
                        'path_code' => $row['path_code'],
                    ]);
            }
        });

        $this->info('Códigos de ubicación sincronizados correctamente.');

        return self::SUCCESS;
    }

    private function buildPathCode(InventoryLocation $location, string $prefix): string
    {
        $segments = [];
        $current = $location;

        while ($current) {
            array_unshift($segments, (string) $current->codigo);
            $current = $current->parent;
        }

        if (empty($segments)) {
            return $prefix.'-'.Str::upper((string) $location->codigo);
        }

        return implode(' / ', $segments);
    }
}
