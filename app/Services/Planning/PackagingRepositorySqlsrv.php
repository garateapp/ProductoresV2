<?php

namespace App\Services\Planning;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
class PackagingRepositorySqlsrv
{
    /**
     * Busca embalajes en SQL Server (SOLO LECTURA).
     *
     * Query obligatoria (según requerimiento):
     * SELECT id, c_item, n_item, tipo_embalaje, CP1, CP2, CP3
     * FROM V_ADM_P_Items
     * WHERE tipo_item like '%IN-EM%' AND n_item like 'ESPECIE%'
     */
    public function searchPackagings(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        Log::debug('Searching packagings with query: '.$q);
        $rows = DB::connection('sqlsrv')
            ->table('V_ADM_P_Items')
            ->select(['id', 'c_item', 'n_item', 'tipo_embalaje', 'tipo_item', 'CP1', 'CP2', 'CP3', 'CP4', 'CP5', 'CP6'])
            // Catálogo para planificación: IN-EM (embalajes) e IN-EN (envases/otros).
            ->whereIn('tipo_item', ['IN-EM', 'IN-EN'])
            ->where(function ($w) use ($q) {
                $w->where('n_item', 'like', '%'.$q.'%')
                    ->orWhere('c_item', 'like', '%'.$q.'%');
            })
            ->orderBy('n_item')
            ->limit($limit)
            ->get();
        Log::debug('Found '.count($rows).' packagings for query: '.$q);
        return collect($rows)->map(function ($row) {
            $cp1 = trim((string) ($row->CP1 ?? ''));
            $cp3 = trim((string) ($row->CP3 ?? ''));
            // Según requerimiento:
            // - CP1 = Etiqueta
            // - CP2 = Envases/Pallet
            // - CP3 = Altura
            $etiqueta = $cp1;
            $altura = $cp3;
            return [
                'id' => $row->id,
                'c_item' => (string) ($row->c_item ?? ''),
                'n_item' => (string) ($row->n_item ?? ''),
                'tipo_embalaje' => $row->tipo_embalaje ?? null,
                'tipo_item' => $row->tipo_item ?? null,
                'cp2_cajas_por_pallet' => $this->normalizeCp2ToInt($row->CP2 ?? null),
                'etiqueta' => $etiqueta !== '' ? $etiqueta : null,
                'altura' => $altura !== '' ? $altura : null,
                'cp1' => $cp1 !== '' ? $cp1 : null, // raw
                'cp3' => $cp3 !== '' ? $cp3 : null, // raw
                'cp4' => isset($row->CP4) ? (string) $row->CP4 : null,
                'cp5' => isset($row->CP5) ? (string) $row->CP5 : null,
                'cp6' => isset($row->CP6) ? (string) $row->CP6 : null,
            ];
        })->filter(fn ($row) => $row['c_item'] !== '' || $row['n_item'] !== '')->values()->all();
    }

    /**
     * Obtiene embalajes por código(s) c_item (SQLSRV) para completar nombre/CP2 desde el catálogo.
     *
     * Query base requerida:
     * SELECT id, c_item, n_item, tipo_embalaje, tipo_item, CP1, CP2, CP3
     * FROM V_ADM_P_Items
     * WHERE tipo_item like '%IN-EM%'
     */
    public function getPackagingsByCodes(array $codes): array
    {
        $list = collect($codes)
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (empty($list)) {
            return [];
        }

        $rows = DB::connection('sqlsrv')
            ->table('V_ADM_P_Items')
            ->select(['id', 'c_item', 'n_item', 'tipo_embalaje', 'tipo_item', 'CP1', 'CP2', 'CP3', 'CP4', 'CP5', 'CP6'])
            ->whereIn('tipo_item', ['IN-EM', 'IN-EN'])
            ->whereIn('c_item', $list)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $cItem = trim((string) ($row->c_item ?? ''));
            if ($cItem === '') {
                continue;
            }
            $cp1 = trim((string) ($row->CP1 ?? ''));
            $cp3 = trim((string) ($row->CP3 ?? ''));
            $etiqueta = $cp1;
            $altura = $cp3;
            $map[$cItem] = [
                'id' => $row->id,
                'c_item' => $cItem,
                'n_item' => (string) ($row->n_item ?? ''),
                'tipo_embalaje' => $row->tipo_embalaje ?? null,
                'tipo_item' => $row->tipo_item ?? null,
                'cp2_cajas_por_pallet' => $this->normalizeCp2ToInt($row->CP2 ?? null),
                'etiqueta' => $etiqueta !== '' ? $etiqueta : null,
                'altura' => $altura !== '' ? $altura : null,
                'cp1' => $cp1 !== '' ? $cp1 : null, // raw
                'cp3' => $cp3 !== '' ? $cp3 : null, // raw
                'cp4' => isset($row->CP4) ? (string) $row->CP4 : null,
                'cp5' => isset($row->CP5) ? (string) $row->CP5 : null,
                'cp6' => isset($row->CP6) ? (string) $row->CP6 : null,
            ];
        }
        return $map;
    }

    public function getPackagingByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $cacheKey = 'planning:packaging_catalog:'.$code;
        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($code) {
            $map = $this->getPackagingsByCodes([$code]);
            return $map[$code] ?? null;
        });
    }

    /**
     * Retorna especies y sus calibres disponibles desde SQL Server.
     * Formato: [ 'Apples' => ['36','40','44',...], 'Apricot' => [...], ... ]
     */
    public function getSpeciesCalibers(): array
    {
        $species = [
            'Apples', 'Apricot', 'Arandanos', 'Caquis', 'Clementinas',
            'Granadas', 'Grapes', 'Kiwis', 'Lemons', 'Mandarinas',
            'Membrillos', 'Orange', 'Paltas', 'Pears',
        ];

        return Cache::remember('planning:packaging-matrix:species-calibers:v1', now()->addHours(4), function () use ($species) {
            $map = [];
            try {
                $rows = DB::connection('sqlsrv')
                    ->table('PRO_P_Calibres AS c')
                    ->join('PRO_P_Calibres_X_Especies AS ce', 'ce.id_pro_p_calibres', '=', 'c.id')
                    ->join('PRO_P_Especies AS e', 'e.id', '=', 'ce.id_pro_p_especies')
                    ->select('e.nombre AS especie', 'c.nombre AS calibre')
                    ->whereIn('e.nombre', $species)
                    ->orderBy('e.nombre')
                    ->orderBy('c.nombre')
                    ->get();

                foreach ($rows as $row) {
                    $esp = trim((string) ($row->especie ?? ''));
                    $cal = trim((string) ($row->calibre ?? ''));
                    if ($esp === '' || $cal === '') {
                        continue;
                    }
                    if (! isset($map[$esp])) {
                        $map[$esp] = [];
                    }
                    $map[$esp][] = $cal;
                }
            } catch (\Throwable $e) {
                Log::warning('getSpeciesCalibers failed: '.$e->getMessage());
            }

            // Asegurar que todas las especies tengan entrada, aunque vacía.
            foreach ($species as $s) {
                if (! isset($map[$s])) {
                    $map[$s] = [];
                }
            }

            return $map;
        });
    }

    private function normalizeCp2ToInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        // CP2 viene a veces con texto/unidades → extraemos el primer número entero.
        if (preg_match('/(\d+)/', $stringValue, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
