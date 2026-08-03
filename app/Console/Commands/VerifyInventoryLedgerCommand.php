<?php

namespace App\Console\Commands;

use App\Services\Inventory\LedgerService;
use Illuminate\Console\Command;

class VerifyInventoryLedgerCommand extends Command
{
    protected $signature = 'inventory:ledger-verify {--from-sequence=}';

    protected $description = 'Verifica la integridad del ledger de inventario';

    public function handle(LedgerService $ledgerService): int
    {
        $result = $ledgerService->verifyChain(
            $this->option('from-sequence') !== null ? (int) $this->option('from-sequence') : null
        );

        if (! ($result['valid'] ?? false)) {
            $this->error($result['message'] ?? 'Ledger inválido.');
            $this->line('Secuencia fallida: '.($result['failed_sequence'] ?? '-'));

            if (isset($result['expected_hash'], $result['found_hash'])) {
                $this->line('Hash esperado: '.$result['expected_hash']);
                $this->line('Hash encontrado: '.$result['found_hash']);
            }

            return self::FAILURE;
        }

        $this->info('Ledger válido.');
        $this->line('Eventos verificados: '.($result['checked'] ?? 0));
        $this->line('Última secuencia: '.($result['last_sequence'] ?? 0));
        $this->line('Último hash: '.($result['last_hash'] ?? str_repeat('0', 64)));

        return self::SUCCESS;
    }
}
