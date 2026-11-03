<?php

namespace App\Console\Commands;

use App\Services\Inventory\SemiFinishedProductionReversalService;
use Illuminate\Console\Command;

class ReconcileLegacySplitReversalsCommand extends Command
{
    protected $signature = 'inventory:reconcile-legacy-reversals
        {--apply : Aplica las devoluciones de insumos; sin esta opción solo simula}
        {--json : Muestra el resultado completo en JSON}
        {--root-id= : ID(s) de producción raíz a procesar, separados por coma}
        {--exclude-root-id= : ID(s) de producción raíz a excluir, separados por coma}
        {--material-code= : Código(s) de material a procesar, separados por coma}
        {--child-lpn= : LPN(s) de hijo a procesar, separados por coma}
        {--partial-quantities= : JSON con cantidades parciales por material_id o material_code, ej: {\"2950\":100,\"IN00200500169\":50}}';

    protected $description = 'Devuelve los insumos faltantes de reversiones históricas de pallets divididos';

    public function handle(SemiFinishedProductionReversalService $service): int
    {
        $apply = (bool) $this->option('apply');
        $filters = $this->parseFilters();
        $partialQuantities = $this->parsePartialQuantities();
        $result = $service->reconcileLegacySplitReversals($apply, $filters, $partialQuantities);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $summary = $result['summary'];

        $this->components->info(sprintf(
            'Modo: %s | Hijos históricos: %d | Raíces: %d',
            $summary['mode'],
            $summary['legacy_child_productions'],
            $summary['root_groups'],
        ));

        if ($result['roots'] === []) {
            $this->components->info('No hay reversiones históricas pendientes de reconciliar.');

            return self::SUCCESS;
        }

        $this->table(
            ['Acción', 'Raíz', 'LPN raíz', 'Hijos', 'Cant. legado', 'Ya devuelto', 'Total tras ajuste', 'Detalle'],
            collect($result['roots'])->map(fn (array $root) => [
                $root['action'],
                $root['root_production_id'],
                $root['root_lpn'] ?? '-',
                count($root['child_production_ids']),
                $this->number($root['legacy_quantity']),
                $this->number($root['already_restored']),
                $this->number($root['restored_after']).' / '.$this->number($root['production_quantity']),
                $root['reason'] ?? 'Devolución de insumos',
            ])->all(),
        );

        foreach ($result['roots'] as $root) {
            if ($root['restoration_lines'] === []) {
                continue;
            }

            $this->newLine();
            $this->line(sprintf(
                '<fg=cyan>Raíz %d · %s · LPNs hijos: %s</>',
                $root['root_production_id'],
                $root['root_lpn'] ?? '-',
                implode(', ', array_filter($root['child_lpns'])),
            ));

            $this->table(
                ['Insumo', 'Cantidad a devolver', 'Movimiento', 'Detalle'],
                collect($root['restoration_lines'])->map(fn (array $line) => [
                    $line['material_code'] ?? $line['material_id'],
                    $this->number($line['quantity']),
                    $line['movement_id'],
                    $line['movement_detail_id'],
                ])->all(),
            );
        }

        if ($result['skipped'] !== []) {
            $this->newLine();
            $this->components->warn('Casos omitidos');
            $this->table(
                ['Hijo', 'LPN', 'Cantidad', 'Motivo'],
                collect($result['skipped'])->map(fn (array $row) => [
                    $row['child_production_id'],
                    $row['lpn'] ?? '-',
                    $this->number($row['quantity']),
                    $row['reason'],
                ])->all(),
            );
        }

        $this->newLine();

        if ($apply) {
            $this->components->info(sprintf(
                'Reconciliación aplicada en %d raíz(es). Stock y ledger actualizados.',
                $summary['applied_roots'],
            ));
        } else {
            $this->components->warn('Dry-run: no se modificó stock. Ejecuta con --apply para aplicar.');
        }

        return self::SUCCESS;
    }

    private function number(float|int|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    }

    /**
     * @return array{root_ids: list<int>, exclude_root_ids: list<int>, material_codes: list<string>, child_lpns: list<string>}
     */
    private function parseFilters(): array
    {
        $parseCsv = fn (?string $value): array => $value === null
            ? []
            : array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== '');

        return [
            'root_ids' => array_map('intval', $parseCsv($this->option('root-id'))),
            'exclude_root_ids' => array_map('intval', $parseCsv($this->option('exclude-root-id'))),
            'material_codes' => $parseCsv($this->option('material-code')),
            'child_lpns' => $parseCsv($this->option('child-lpn')),
        ];
    }

    /**
     * @return array<string|int, float>
     */
    private function parsePartialQuantities(): array
    {
        $raw = $this->option('partial-quantities');

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            $this->components->error('El valor de --partial-quantities no es un JSON válido.');
            return self::FAILURE;
        }

        $result = [];
        foreach ($decoded as $key => $value) {
            $result[$key] = (float) $value;
        }

        return $result;
    }
}
