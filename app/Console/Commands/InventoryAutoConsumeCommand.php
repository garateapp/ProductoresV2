<?php

namespace App\Console\Commands;

use App\Services\Inventory\AutoConsumptionService;
use Illuminate\Console\Command;

class InventoryAutoConsumeCommand extends Command
{
    protected $signature = 'inventory:auto-consume
                            {--dry-run : Muestra el resultado sin persistir cambios}
                            {--limit=200 : Máximo de folios a revisar desde la vista}
                            {--folio= : Procesa únicamente un folio específico}';

    protected $description = 'Consume materiales automáticamente desde los folios de V_PKG_Produccion_Salidas cada 5 minutos';

    public function handle(AutoConsumptionService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $folio = $this->option('folio') ?: null;

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Consultando folios de producción...');

        $results = $service->run($limit, $folio, $dryRun);

        if ($results === []) {
            $this->line('No hay folios nuevos por procesar.');
            $this->newLine();

            if ($dryRun) {
                $this->warn('(DRY RUN) Nada se persistió.');
            }

            return self::SUCCESS;
        }

        $applied = collect($results)->where('estado', 'aplicado')->count();
        $borrador = collect($results)->where('estado', 'borrador')->count();
        $rejected = collect($results)->whereNotIn('estado', ['aplicado', 'borrador'])->count();

        $this->table(
            ['Folio', 'Embalaje', 'Cajas', 'Línea', 'Turno', 'Estado', 'Detalle'],
            collect($results)->map(fn (array $r) => [
                $r['folio'],
                $r['c_embalaje'],
                $r['cantidad'],
                $r['linea'] ?? '-',
                $r['turno'] ?? '-',
                $r['estado'],
                $r['detalle_estado'] ?? '-',
            ])->values()->all()
        );

        $this->newLine();
        $this->info("Resumen: {$applied} aplicado(s), {$borrador} en borrador, {$rejected} rechazado(s).");

        if ($dryRun) {
            $this->warn('(DRY RUN) Nada se persistió.');
        }

        return self::SUCCESS;
    }
}