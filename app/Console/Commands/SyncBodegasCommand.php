<?php

namespace App\Console\Commands;

use App\Services\PreCooling\BodegaSyncService;
use Illuminate\Console\Command;

class SyncBodegasCommand extends Command
{
    protected $signature = 'prefrio:sync-bodegas';

    protected $description = 'Sincronizar bodegas desde ADM_P_Bodegas en SQL Server';

    public function handle(BodegaSyncService $sync): int
    {
        $this->info('Sincronizando bodegas desde SQL Server...');

        try {
            $result = $sync->sync();

            $this->info("Creadas: {$result['created']}, Actualizadas: {$result['updated']}, Omitidas: {$result['skipped']}, Total: {$result['total']}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
