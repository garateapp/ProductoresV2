<?php

namespace App\Services\Garces;

use App\Models\GarcesCodeMapping;
use App\Models\IntegrationPendingMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GarcesCodeDictionaryService
{
    public const TYPE_SUBSIDIARY = 'subsidiary';

    public const TYPE_SEASON = 'season';

    public const TYPE_TIPO_TARJA = 'tipo_tarja';

    public const TYPE_TIPO_FRUTA = 'tipo_fruta';

    public const TYPE_TIPO_PALLET = 'tipo_pallet';

    public const TYPE_ESPECIE = 'especie';

    public const TYPE_VARIEDAD = 'variedad';

    public const TYPE_ENVASE = 'envase';

    public const TYPE_EMBALAJE = 'embalaje';

    public const TYPE_ETIQUETA = 'etiqueta';

    public const TYPE_CATEGORIA = 'categoria';

    public const TYPE_CALIBRE = 'calibre';

    public const TYPE_CLASIFICACION = 'clasificacion';

    private const CACHE_TTL_HOURS = 6;

    /**
     * @return array<int,string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_SUBSIDIARY,
            self::TYPE_SEASON,
            self::TYPE_TIPO_TARJA,
            self::TYPE_TIPO_FRUTA,
            self::TYPE_TIPO_PALLET,
            self::TYPE_ESPECIE,
            self::TYPE_VARIEDAD,
            self::TYPE_ENVASE,
            self::TYPE_EMBALAJE,
            self::TYPE_ETIQUETA,
            self::TYPE_CATEGORIA,
            self::TYPE_CALIBRE,
            self::TYPE_CLASIFICACION,
        ];
    }

    /**
     * Traduce un valor interno al código Garces. Acepta clave simple o compuesta
     * (array). Para claves compuestas prueba "parte1|parte2" y luego cada parte
     * de la más específica (última) a la más general.
     *
     * @param  string|array<int,string>|null  $clave
     */
    public function translate(string $tipo, string|array|null $clave, ?string $referenceDate = null): ?string
    {
        $candidates = $this->candidateKeys($clave);

        if ($candidates === []) {
            return null;
        }

        $date = $referenceDate ?? now()->format('Y-m-d');
        $map = $this->loadMap($tipo);

        foreach ($candidates as $candidate) {
            foreach ($map as $row) {
                if ($row['clave'] !== $candidate) {
                    continue;
                }

                if ($row['desde'] !== null && $row['desde'] > $date) {
                    continue;
                }

                if ($row['hasta'] !== null && $row['hasta'] < $date) {
                    continue;
                }

                return $row['garces'];
            }
        }

        return null;
    }

    /**
     * @param  array<string,string|array<int,string>|null>  $values
     * @return array<string,?string>
     */
    public function translateMany(array $values, ?string $referenceDate = null): array
    {
        $result = [];

        foreach ($values as $tipo => $clave) {
            $result[$tipo] = $this->translate($tipo, $clave, $referenceDate);
        }

        return $result;
    }

    /**
     * Registra un valor interno sin traducción en la cola de pendientes.
     *
     * @param  string|array<int,string>|null  $clave
     */
    public function missing(string $tipo, string|array|null $clave, ?string $observacion = null): void
    {
        $valor = $this->canonicalValue($clave);

        if ($valor === null) {
            return;
        }

        try {
            $clientId = $this->pendingClientId();

            $pending = IntegrationPendingMapping::firstOrNew([
                'client_id' => $clientId,
                'campo' => $tipo,
                'valor_interno' => $valor,
            ]);

            $pending->frecuencia = $pending->exists ? (int) $pending->frecuencia + 1 : 1;
            $pending->resolved_at = null;

            if ($observacion !== null) {
                $pending->observacion = $observacion;
            }

            $pending->save();
        } catch (\Throwable $e) {
            Log::warning('GarcesCodeDictionary: no se pudo registrar pending mapping', [
                'tipo' => $tipo,
                'valor' => $valor,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resuelve el código Garces o, si no existe, deja el valor en pendientes.
     *
     * @param  string|array<int,string>|null  $clave
     */
    public function resolveOrMissing(string $tipo, string|array|null $clave, ?string $referenceDate = null): ?string
    {
        $resolved = $this->translate($tipo, $clave, $referenceDate);

        if ($resolved === null) {
            $this->missing($tipo, $clave);
        }

        return $resolved;
    }

    /**
     * @param  string|array<int,string>|null  $clave
     * @return array<int,string>
     */
    private function candidateKeys(string|array|null $clave): array
    {
        if ($clave === null) {
            return [];
        }

        $parts = is_array($clave) ? $clave : [$clave];

        $parts = array_values(array_filter(
            array_map(fn ($value) => trim((string) $value), $parts),
            fn (string $value) => $value !== ''
        ));

        if ($parts === []) {
            return [];
        }

        $keys = [];

        if (count($parts) > 1) {
            $keys[] = implode('|', $parts);
        }

        foreach (array_reverse($parts) as $part) {
            if (! in_array($part, $keys, true)) {
                $keys[] = $part;
            }
        }

        return $keys;
    }

    /**
     * @param  string|array<int,string>|null  $clave
     */
    private function canonicalValue(string|array|null $clave): ?string
    {
        if ($clave === null) {
            return null;
        }

        $parts = is_array($clave) ? $clave : [$clave];

        $parts = array_values(array_filter(
            array_map(fn ($value) => trim((string) $value), $parts),
            fn (string $value) => $value !== ''
        ));

        if ($parts === []) {
            return null;
        }

        return count($parts) > 1 ? implode('|', $parts) : $parts[0];
    }

    /**
     * @return array<int,array{clave:string,garces:string,desde:?string,hasta:?string}>
     */
    private function loadMap(string $tipo): array
    {
        $version = GarcesCodeMapping::cacheVersion();
        $key = "garces_code_dict:v{$version}:{$tipo}";

        return Cache::remember($key, now()->addHours(self::CACHE_TTL_HOURS), function () use ($tipo) {
            return GarcesCodeMapping::query()
                ->tipo($tipo)
                ->activo()
                ->orderByDesc('prioridad')
                ->get(['clave_interna', 'clave_garces', 'vigencia_desde', 'vigencia_hasta'])
                ->map(fn (GarcesCodeMapping $mapping) => [
                    'clave' => trim((string) $mapping->clave_interna),
                    'garces' => (string) $mapping->clave_garces,
                    'desde' => $mapping->vigencia_desde?->format('Y-m-d'),
                    'hasta' => $mapping->vigencia_hasta?->format('Y-m-d'),
                ])
                ->all();
        });
    }

    private function pendingClientId(): int
    {
        $code = (string) config('services.garces_pallet_box.integration_client_code', 'GARCES-PALLETBOX');

        $clientId = DB::table('integration_clients')->where('codigo', $code)->value('id');

        if ($clientId) {
            return (int) $clientId;
        }

        $now = now();

        return (int) DB::table('integration_clients')->insertGetId([
            'codigo' => $code,
            'nombre' => 'Garces Fruit (Carga de pallets por caja)',
            'descripcion' => 'Cliente tecnico para la integracion de carga de pallets por caja (RegisterBoxes).',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
