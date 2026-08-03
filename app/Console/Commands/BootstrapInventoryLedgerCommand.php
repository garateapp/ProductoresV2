<?php

namespace App\Console\Commands;

use App\Models\InventoryLedgerEvent;
use App\Models\InventoryStockLocation;
use App\Models\User;
use App\Services\Inventory\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapInventoryLedgerCommand extends Command
{
    protected $signature = 'inventory:ledger-bootstrap {--user-id=} {--dry-run}';

    protected $description = 'Crea línea base del ledger usando el stock actual por ubicación';

    public function handle(LedgerService $ledgerService): int
    {
        $existingEvents = InventoryLedgerEvent::query()->count();
        if ($existingEvents > 0) {
            $this->error('El ledger ya contiene eventos. No se puede bootstrappear nuevamente.');
            return self::FAILURE;
        }

        $userId = $this->option('user-id') !== null
            ? (int) $this->option('user-id')
            : (int) (User::query()->orderBy('id')->value('id') ?? 0);

        if ($userId <= 0 || ! User::query()->whereKey($userId)->exists()) {
            $this->error('No existe un usuario válido para registrar el baseline del ledger.');
            return self::FAILURE;
        }

        $rows = InventoryStockLocation::query()
            ->with(['location:id,codigo,nombre', 'material:id,codigo,nombre'])
            ->where('stock_actual', '!=', 0)
            ->orderBy('location_id')
            ->orderBy('material_id')
            ->get();

        $this->info('Posiciones a baselinear: '.$rows->count());

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows, $userId, $ledgerService): void {
            foreach ($rows as $row) {
                $quantity = (float) $row->stock_actual;
                $ledgerService->append([
                    'event_type' => 'GENESIS_BALANCE',
                    'movement_id' => null,
                    'movement_detail_id' => null,
                    'allocation_id' => null,
                    'material_id' => $row->material_id,
                    'location_id' => $row->location_id,
                    'logistic_unit_id' => null,
                    'signed_quantity' => $quantity,
                    'stock_effect' => $quantity >= 0 ? 'in' : 'out',
                    'payload' => [
                        'baseline' => true,
                        'material_codigo' => $row->material?->codigo,
                        'material_nombre' => $row->material?->nombre,
                        'location_codigo' => $row->location?->codigo,
                        'location_nombre' => $row->location?->nombre,
                        'stock_actual' => $quantity,
                    ],
                    'occurred_at' => now(),
                    'actor_user_id' => $userId,
                    'actor_name_snapshot' => User::query()->whereKey($userId)->value('name'),
                    'device_code' => 'bootstrap',
                ]);
            }
        });

        $this->info('Baseline del ledger creado correctamente.');
        $this->line('Eventos creados: '.$rows->count());

        return self::SUCCESS;
    }
}
