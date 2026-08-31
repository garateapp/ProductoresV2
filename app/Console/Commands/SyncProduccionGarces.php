<?php

namespace App\Console\Commands;

use App\Services\ProduccionGarcesSyncService;
use Illuminate\Console\Command;

class SyncProduccionGarces extends Command
{
    protected $signature = 'sync:produccion-garces';

    protected $description = 'Envia registros nuevos de produccion al endpoint de Garces Fruits';

    public function handle(ProduccionGarcesSyncService $service): int
    {
        $this->info('Iniciando sincronizacion de produccion a Garces Fruits...');

        $result = $service->execute();

        $status = $result['status'] ?? 'unknown';
        $sent = $result['sent'] ?? 0;
        $failed = $result['failed'] ?? 0;

        match ($status) {
            'disabled' => $this->warn('Servicio deshabilitado (GARCES_SYNC_ENABLED=false)'),
            'error' => $this->error('Error: '.$result['message'] ?? 'Desconocido'),
            'completed' => $this->info("Completado: {$sent} enviados, {$failed} fallidos"),
            default => $this->line("Estado: {$status}"),
        };

        return $failed > 0 || $status === 'error' ? Command::FAILURE : Command::SUCCESS;
    }
}
