<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProducersActive extends Command
{
    protected $signature = 'producers:sync-active {--dry-run : No persiste cambios (simulación)}';

    protected $description = 'Sincroniza el estado activo (is_active) de productores desde SQL Server (sqlsrv)';

    public function handle(): int
    {
        $this->info('Consultando productores activos desde FX...');

        $rows = DB::connection('sqlsrv')
            ->table('ADM_P_Entidades as E')
            ->join('ADM_P_Entidades_X_Tipo as ET', 'E.id', '=', 'ET.id_adm_p_entidades')
            ->where('ET.id_adm_p_tipo_entidades', 1)
            ->where('E.CP1', '1')
            ->select(['E.rut', 'E.codigo_sag'])
            ->get();

        $normalizeRut = function ($rut) {
            if ($rut === null) return null;
            $s = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
            return $s;
        };
        $normalizeCsg = function ($csg) {
            return trim((string) $csg);
        };

        $validPairs = [];
        foreach ($rows as $r) {
            $key = $normalizeRut($r->rut).'|'.$normalizeCsg($r->codigo_sag);
            $validPairs[$key] = true;
        }

        $this->info('Se obtuvieron '.count($validPairs).' pares (RUT, CSG) válidos. Sincronizando usuarios locales...');

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0; $examined = 0; $activate = 0; $deactivate = 0;
        $changes = [];

        User::role('Productor')->chunkById(500, function ($users) use (&$updated, &$examined, &$activate, &$deactivate, $validPairs, $normalizeRut, $normalizeCsg, $dryRun) {
            foreach ($users as $u) {
                $examined++;
                $pairKey = $normalizeRut($u->rut).'|'.$normalizeCsg($u->csg);
                $shouldBeActive = !empty($u->idprod) && isset($validPairs[$pairKey]);
                if ((bool) $u->is_active !== $shouldBeActive) {
                    $updated++;
                    $shouldBeActive ? $activate++ : $deactivate++;
                    $changes[] = [
                        'id' => $u->id,
                        'name' => $u->name,
                        'rut' => $u->rut,
                        'csg' => $u->csg,
                        'from' => (bool) $u->is_active,
                        'to' => $shouldBeActive,
                    ];
                    if (! $dryRun) {
                        $u->is_active = $shouldBeActive;
                        $u->save();
                    }
                }
            }
        });

        $this->info("Analizados: {$examined}, Cambios: {$updated}, Activados: {$activate}, Desactivados: {$deactivate}.".
            ($dryRun ? ' (simulación)' : ''));

        if ($updated === 0) {
            $this->line('No se requieren cambios.');
        } else {
            $this->line('Detalle de cambios'.($dryRun ? ' (simulación)' : '').':');
            $limit = 200; // avoid flooding output
            $shown = 0;
            foreach ($changes as $c) {
                $from = $c['from'] ? 'ACTIVO' : 'INACTIVO';
                $to = $c['to'] ? 'ACTIVO' : 'INACTIVO';
                $this->line(sprintf('- #%d %s | RUT:%s | CSG:%s | %s -> %s', $c['id'], $c['name'], $c['rut'], $c['csg'], $from, $to));
                $shown++;
                if ($shown >= $limit) { break; }
            }
            if ($updated > $shown) {
                $this->line(sprintf('... y %d cambios más.', $updated - $shown));
            }
        }

        // Show post-sync totals for visibility
        try {
            $activeCount = User::role('Productor')->where('is_active', true)->count();
            $inactiveCount = User::role('Productor')->where('is_active', false)->count();
            $this->line(sprintf('Totales ahora -> Activos: %d | Inactivos: %d', $activeCount, $inactiveCount));
        } catch (\Throwable $e) {
            // ignore counting errors
        }

        return self::SUCCESS;
    }
}
