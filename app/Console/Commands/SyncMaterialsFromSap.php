<?php

namespace App\Console\Commands;

use App\Services\Inventory\MaterialCatalogService;
use Illuminate\Console\Command;

class SyncMaterialsFromSap extends Command
{
    protected $signature = 'materials:sync-sap';

    protected $description = 'Sincroniza el catálogo y stock de materiales desde SAP (Service Layer o SQL).';

    public function handle(MaterialCatalogService $catalogService): int
    {
        $summary = $catalogService->syncFromSap();

        $message = "SAP sincronizado. {$summary['total']} filas, {$summary['created']} creados, {$summary['updated']} actualizados";

        if (($summary['zeroed'] ?? 0) > 0) {
            $message .= ", {$summary['zeroed']} sin stock en SAP puestos en 0";
        }

        $this->info($message.'.');

        return Command::SUCCESS;
    }
}
