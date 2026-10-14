<?php

namespace App\Services\Garces;

use App\Models\GarcesCodeMapping;
use Illuminate\Support\Facades\Log;

/**
 * Convierte mapeos pendientes ya resueltos (cola generica de integraciones)
 * en entradas del diccionario Garces, para que la siguiente ejecucion los use.
 */
class GarcesPendingMappingBridge
{
    /**
     * @param  array<int,array{client_code?:?string,campo?:?string,valor_interno?:?string,valor_asignado?:?string}>  $rows
     * @return int Cantidad de entradas del diccionario creadas/actualizadas.
     */
    public function sync(array $rows): int
    {
        $clientCode = (string) config('services.garces_pallet_box.integration_client_code', 'GARCES-PALLETBOX');
        $types = GarcesCodeDictionaryService::types();
        $count = 0;

        foreach ($rows as $row) {
            $rowClient = $row['client_code'] ?? null;

            if ($rowClient !== null && $rowClient !== $clientCode) {
                continue;
            }

            $campo = trim((string) ($row['campo'] ?? ''));
            $valorInterno = trim((string) ($row['valor_interno'] ?? ''));
            $valorAsignado = trim((string) ($row['valor_asignado'] ?? ''));

            if (! in_array($campo, $types, true) || $valorInterno === '' || $valorAsignado === '') {
                continue;
            }

            try {
                GarcesCodeMapping::updateOrCreate(
                    [
                        'tipo' => $campo,
                        'clave_interna' => $valorInterno,
                        'clave_garces' => $valorAsignado,
                    ],
                    ['activo' => true]
                );

                $count++;
            } catch (\Throwable $e) {
                Log::warning('GarcesPendingMappingBridge: no se pudo sincronizar el mapeo resuelto', [
                    'campo' => $campo,
                    'valor_interno' => $valorInterno,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
