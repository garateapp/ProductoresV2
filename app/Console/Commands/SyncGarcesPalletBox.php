<?php

namespace App\Console\Commands;

use App\Services\Garces\PalletBox\GarcesPalletBoxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncGarcesPalletBox extends Command
{
    protected $signature = 'sync:pallet-box {--batch=100} {--dry-run} {--folio=} {--output=}';

    protected $description = 'Sincroniza carga de pallets por caja con Garces (RegisterBoxes)';

    public function handle(GarcesPalletBoxService $service): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun($service);
        }

        $batch = (int) $this->option('batch');
        $result = $service->syncPending($batch);

        $this->info("Enviados: {$result['sent']}, Rechazados: {$result['rejected']}, Errores: {$result['errors']}");

        return self::SUCCESS;
    }

    private function dryRun(GarcesPalletBoxService $service): int
    {
        $folio = $this->option('folio');
        $folio = is_string($folio) && trim($folio) !== '' ? trim($folio) : null;

        $preview = $service->preview($folio);

        $this->info('DRY RUN — no se envía nada a Garces.');
        $this->line('Endpoint: '.($preview['endpoint'] ?? '(base_url no configurada)'));
        $this->line("Tolerancia kilos: {$preview['tolerance']}");

        if ($preview['count'] === 0) {
            $this->warn($folio !== null
                ? "No hay datos para el folio {$folio}."
                : 'No hay pallets para previsualizar.');

            return self::SUCCESS;
        }

        $this->line("Pallets: {$preview['count']} | Cajas: {$preview['boxes']}");

        $output = $this->option('output');
        $path = is_string($output) && trim($output) !== '' ? trim($output) : null;

        if ($path === null) {
            foreach ($preview['pallets'] as $index => $pallet) {
                $this->newLine();
                $this->line('--- ['.($index + 1).'/'.$preview['count'].'] folio '.$pallet['folio'].' ---');
                $this->output->writeln($this->encode($pallet['payload']));
            }
        }

        if ($path !== null) {
            File::put($path, $this->encode($preview).PHP_EOL);

            $this->newLine();
            $this->info("Payloads guardados en: {$path}");
        }

        return self::SUCCESS;
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
