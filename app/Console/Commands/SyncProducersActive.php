<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyncProducersActive extends Command
{
    protected $signature = 'producers:sync-active {--dry-run : No persiste cambios (simulación)}';

    protected $description = 'Sincroniza productores desde SQL Server, creando faltantes y actualizando estado activo';

    public function handle(): int
    {
        $this->info('Consultando productores desde FX...');

        $activePairs = DB::connection('sqlsrv')
            ->table('ADM_P_Entidades as E')
            ->join('ADM_P_Entidades_X_Tipo as ET', 'E.id', '=', 'ET.id_adm_p_entidades')
            ->where('ET.id_adm_p_tipo_entidades', 1)
            ->where('E.CP1', '1')
            ->select(['E.rut', 'E.codigo_sag'])
            ->get();

        $allRemoteProducers = DB::connection('sqlsrv')
            ->table('ADM_P_Entidades')
            ->select(['id', 'rut', 'nombre', 'direccion', 'codigo_sag'])
            ->where('tipo_juridico', 1)
            ->where('codigo_sag', '!=', null)
            ->where('codigo_sag', '!=', '')
            ->where('CP1', '1')
            ->get();

        $normalizeRut = static function ($rut) {
            if ($rut === null) {
                return null;
            }

            return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
        };

        $normalizeCsg = static function ($csg) {
            return trim((string) $csg);
        };

        $validPairs = [];
        foreach ($activePairs as $row) {
            $key = $normalizeRut($row->rut) . '|' . $normalizeCsg($row->codigo_sag);
            $validPairs[$key] = true;
        }

        $this->info(
            sprintf(
                'Se obtuvieron %d productores desde FX y %d pares (RUT/CSG) activos.',
                count($allRemoteProducers),
                count($validPairs)
            )
        );

        $dryRun = (bool) $this->option('dry-run');

        $existingUsers = User::role('Productor')->get(['id', 'name', 'rut', 'csg', 'email']);
        $existingMap = [];
        $existingEmails = [];
        foreach ($existingUsers as $user) {
            $key = $normalizeRut($user->rut) . '|' . $normalizeCsg($user->csg);
            if ($key !== '|') {
                $existingMap[$key] = $user;
            }

            if (! empty($user->email)) {
                $existingEmails[strtolower($user->email)] = true;
            }
        }

        $created = 0;
        $createdSummaries = [];
        foreach ($allRemoteProducers as $remoteProducer) {
            $rutKey = $normalizeRut($remoteProducer->rut);
            $csgKey = $normalizeCsg($remoteProducer->codigo_sag);

            if (! $rutKey || ! $csgKey) {
                continue;
            }

            $pairKey = $rutKey . '|' . $csgKey;
            if (isset($existingMap[$pairKey])) {
                continue;
            }

            $created++;
            $createdSummaries[] = [
                'idprod' => $remoteProducer->id,
                'name' => $remoteProducer->nombre,
                'rut' => $remoteProducer->rut,
                'csg' => $remoteProducer->codigo_sag,
            ];

            if ($dryRun) {
                continue;
            }

            $email = $this->makeSyncEmail($remoteProducer, $existingEmails);
            $password = Hash::make(Str::random(16));

            $user = User::create([
                'name' => $remoteProducer->nombre,
                'email' => $email,
                'password' => $password,
                'rut' => $remoteProducer->rut,
                'idprod' => (string) $remoteProducer->id,
                'csg' => $remoteProducer->codigo_sag,
                'direccion' => $remoteProducer->direccion,
                'is_active' => false,
                'emnotification' => false,
            ]);

            $user->assignRole('Productor');

            $existingMap[$pairKey] = $user;
            $existingEmails[strtolower($email)] = true;
        }

        $updated = 0;
        $examined = 0;
        $activate = 0;
        $deactivate = 0;
        $changes = [];

        User::role('Productor')->chunkById(500, function ($users) use (&$updated, &$examined, &$activate, &$deactivate, &$changes, $validPairs, $normalizeRut, $normalizeCsg, $dryRun) {
            foreach ($users as $user) {
                $examined++;
                $pairKey = $normalizeRut($user->rut) . '|' . $normalizeCsg($user->csg);
                $shouldBeActive = ! empty($user->idprod) && isset($validPairs[$pairKey]);

                if ((bool) $user->is_active === $shouldBeActive) {
                    continue;
                }

                $updated++;
                $shouldBeActive ? $activate++ : $deactivate++;

                $changes[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'rut' => $user->rut,
                    'csg' => $user->csg,
                    'from' => (bool) $user->is_active,
                    'to' => $shouldBeActive,
                ];

                if ($dryRun) {
                    continue;
                }

                $user->is_active = $shouldBeActive;
                $user->save();
            }
        });

        if ($created > 0) {
            $this->info(sprintf('Productores nuevos detectados: %d%s', $created, $dryRun ? ' (simulación)' : ''));
            $limitNew = 100;
            foreach (array_slice($createdSummaries, 0, $limitNew) as $summary) {
                $this->line(sprintf(
                    '- Nuevo productor idprod:%s | RUT:%s | CSG:%s | Nombre:%s',
                    $summary['idprod'],
                    $summary['rut'],
                    $summary['csg'],
                    $summary['name']
                ));
            }
            if ($created > $limitNew) {
                $this->line(sprintf('... y %d productores adicionales.', $created - $limitNew));
            }
        } else {
            $this->info('No se detectaron productores nuevos.');
        }

        $this->info("Analizados: {$examined}, Cambios estado: {$updated}, Activados: {$activate}, Desactivados: {$deactivate}" . ($dryRun ? ' (simulación)' : ''));

        if ($updated > 0) {
            $this->line('Detalle de cambios' . ($dryRun ? ' (simulación)' : '') . ':');
            $limit = 200;
            foreach (array_slice($changes, 0, $limit) as $change) {
                $from = $change['from'] ? 'ACTIVO' : 'INACTIVO';
                $to = $change['to'] ? 'ACTIVO' : 'INACTIVO';
                $this->line(sprintf(
                    '- #%d %s | RUT:%s | CSG:%s | %s -> %s',
                    $change['id'],
                    $change['name'],
                    $change['rut'],
                    $change['csg'],
                    $from,
                    $to
                ));
            }
            if ($updated > $limit) {
                $this->line(sprintf('... y %d cambios más.', $updated - $limit));
            }
        } else {
            $this->line('No se requieren cambios de estado.');
        }

        try {
            $activeCount = User::role('Productor')->where('is_active', true)->count();
            $inactiveCount = User::role('Productor')->where('is_active', false)->count();
            $this->line(sprintf('Totales ahora -> Activos: %d | Inactivos: %d', $activeCount, $inactiveCount));
        } catch (\Throwable $e) {
            // ignore counting errors
        }

        return self::SUCCESS;
    }

    private function makeSyncEmail(object $remoteProducer, array $existingEmails): string
    {
        $base = 'producer-' . $remoteProducer->id . '-' . $remoteProducer->codigo_sag;
        $email = strtolower($base) . '@sync.greenex.cl';

        if (! isset($existingEmails[$email])) {
            return $email;
        }

        do {
            $email = strtolower($base) . '-' . Str::lower(Str::random(4)) . '@sync.greenex.cl';
        } while (isset($existingEmails[$email]));

        return $email;
    }
}
