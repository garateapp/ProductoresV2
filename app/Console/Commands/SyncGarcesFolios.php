<?php

namespace App\Console\Commands;

use App\Services\Garces\GarcesProduccionFolioService;
use Illuminate\Console\Command;

class SyncGarcesFolios extends Command
{
    protected $signature = 'sync:garces-folios
                            {--folio=* : Folios a enviar (repetible o separado por comas)}
                            {--dry-run : Genera el JSON RegisterBoxes sin enviarlo}
                            {--limit=25 : Cantidad de folios pendientes a listar}';

    protected $description = 'Envía a Garces (RegisterBoxes) solo los folios de producción seleccionados';

    public function handle(GarcesProduccionFolioService $service): int
    {
        $folios = $this->normalize($this->option('folio'));

        if ($folios === []) {
            return $this->listarPendientes($service, (int) $this->option('limit'));
        }

        if ($this->option('dry-run')) {
            $result = $service->previewFolios($folios);

            $this->info('DRY RUN — no se envía nada a Garces.');
            $this->line('Endpoint: '.($result['endpoint'] ?? '(no configurado)'));
            $this->line((string) ($result['message'] ?? ''));

            $payload = $result['payload'] ?? null;

            if (! is_array($payload) || ($payload['pallets'] ?? []) === []) {
                $this->error((string) ($result['message'] ?? 'No se pudo generar el JSON.'));

                return self::FAILURE;
            }

            $this->newLine();
            $this->line('--- JSON a enviar ('.count($payload['pallets']).' pallet(s), todos los folios) ---');
            $this->output->writeln($this->encode($payload));

            if (($result['folios_without_boxes'] ?? []) !== []) {
                $this->warn('Sin cajas en el origen: '.implode(', ', $result['folios_without_boxes']));
            }

            if (($result['missing_folios'] ?? []) !== []) {
                $this->warn('Sin datos: '.implode(', ', $result['missing_folios']));
            }

            return self::SUCCESS;
        }

        $result = $service->syncFolios($folios);
        $status = (string) ($result['status'] ?? 'unknown');

        match ($status) {
            'disabled' => $this->warn((string) ($result['message'] ?? 'Servicio deshabilitado.')),
            'error' => $this->error((string) ($result['message'] ?? 'Error desconocido.')),
            default => $this->info((string) ($result['message'] ?? "Estado: {$status}")),
        };

        return in_array($status, ['completed', 'partial'], true) ? self::SUCCESS : self::FAILURE;
    }

    private function listarPendientes(GarcesProduccionFolioService $service, int $limit): int
    {
        $limit = max(1, $limit);
        $filters = [
            'desde' => now()->subDays(7)->toDateString(),
            'hasta' => now()->toDateString(),
            'estado' => 'pendientes',
        ];

        $page = $service->availableFolios($filters, $limit);

        if ($service->sourceError !== null) {
            $this->error('No se pudo leer la vista de producción: '.$service->sourceError);

            return self::FAILURE;
        }

        if ($page->total() === 0) {
            $this->info('No hay folios pendientes en los últimos 7 días.');

            return self::SUCCESS;
        }

        $this->table(
            ['Folio', 'Fecha', 'Proceso', 'Productor', 'Cajas', 'Kilos'],
            collect($page->items())->map(fn ($row) => [
                $row['folio'],
                $row['fecha_proceso'],
                $row['numero_proceso'],
                $row['productor'],
                $row['cajas'],
                $row['kilos'],
            ])->all(),
        );

        $this->newLine();
        $this->line('Muestra de '.$page->count().' de '.$page->total().' folio(s) pendientes (últimos 7 días).');
        $this->line('Envía los seleccionados con: php artisan sync:garces-folios --folio=FOLIO');
        $this->line('Previsualiza el JSON con: php artisan sync:garces-folios --folio=FOLIO --dry-run');

        return self::SUCCESS;
    }

    /**
     * @param  array<int,string>|string  $folios
     * @return array<int,string>
     */
    private function normalize(array|string $folios): array
    {
        $folios = is_array($folios) ? $folios : [$folios];

        return collect($folios)
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
