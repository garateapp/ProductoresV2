<?php

namespace App\Console\Commands;

use App\Services\Inventory\MaterialCatalogService;
use Illuminate\Console\Command;

class SyncMaterialsFromSap extends Command
{
    protected $signature = 'materials:sync-sap {desde? : Fecha inicial YYYYMMDD} {hasta? : Fecha final YYYYMMDD}';

    protected $description = 'Sincroniza el catálogo de materiales desde SAP (Service Layer o SQL).';

    public function handle(MaterialCatalogService $catalogService): int
    {
        $summary = $catalogService->syncFromSap(
            $this->argument('desde'),
            $this->argument('hasta')
        );

        $this->info(
            "SAP sincronizado. {$summary['total']} filas, {$summary['created']} creados, {$summary['updated']} actualizados."
        );

        return Command::SUCCESS;
    }
}
