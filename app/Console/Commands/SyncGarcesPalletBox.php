<?php

namespace App\Console\Commands;

use App\Services\Garces\PalletBox\GarcesPalletBoxService;
use Illuminate\Console\Command;

class SyncGarcesPalletBox extends Command
{
    protected $signature = 'sync:pallet-box {--batch=100}';

    protected $description = 'Sincroniza carga de pallets por caja con Garces (RegisterBoxes)';

    public function handle(GarcesPalletBoxService $service): int
    {
        $batch = (int) $this->option('batch');
        $result = $service->syncPending($batch);

        $this->info("Enviados: {$result['sent']}, Rechazados: {$result['rejected']}, Errores: {$result['errors']}");

        return self::SUCCESS;
    }
}
