<?php

namespace App\Services\Planning;

use App\Models\PackagingMatrixRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve embalaje sugerido usando la matriz CSV de carozos.
 *
 * Objetivo: que sea mantenible (editar CSV) y útil en planta (auto-sugerencia con fallback manual).
 *
 * La matriz decide el código de embalaje (c_item). El nombre/CP2 se completa desde SQLSRV
 * vía PackagingRepositorySqlsrv.
 */
class CarozosPackagingMatrixService
{
    private const CHERRIES_CALIBRES = [
        'L', 'LD', 'XL', 'XLD', 'J', 'JD', '2J', '2JD', '3J', '3JD', '4J', '4JD', '5J', '5JD', '6J', '6JD', '7J', '7JD',
    ];

    private const DESTINOS_CONOCIDOS = [
        'MEXICO',
        'CHINA',
        'LATAM',
        'EUROPA',
        'TAIWAN',
        'USA',
        'EEUU',
        'E.E.U.U',
        'UK',
        'REINO UNIDO',
    ];

    public function __construct(private readonly PackagingRepositorySqlsrv $packagingRepository)
    {
    }

    /**
     * Sugiere embalaje para un lote (inventario + snapshot de calidad).
     *
     * @param array $inventoryRow  fila desde InventoryRepositorySqlsrv::getAvailableLots()
     * @param array $qualitySnap   salida de QualityRepositoryMysql::getQualityByNGRecepcion() para el n_g_recepcion
     * @return array|null          ['c_item','n_item','cp2_cajas_por_pallet','matched_rule'=>array]
     */
    public function suggest(array $inventoryRow, array $qualitySnap = []): ?array
    {
        $matrix = $this->matrixForInventoryRow($inventoryRow);
        Log::debug('Planning packaging suggest (start)', [
            'n_g_recepcion' => $inventoryRow['n_g_recepcion'] ?? null,
            'especie' => $inventoryRow['especie'] ?? null,
            'variedad' => $inventoryRow['variedad'] ?? null,
            'matrix' => $matrix,
        ]);
        $rules = $this->rules($matrix);
        if (empty($rules)) {
            return null;
        }
        Log::debug('Planning packaging suggest (rules)', [
            'n_g_recepcion' => $inventoryRow['n_g_recepcion'] ?? null,
            'matrix' => $matrix,
            'rules_count' => count($rules),
        ]);
        $ctx = $this->buildContext($inventoryRow, $qualitySnap);
        if ($ctx['especie'] === '') {
            return null;
        }

        $matched = $this->findFirstMatch($rules, $ctx);
        if (! $matched) {
            return null;
        }

        $code = (string) ($matched['embalaje_code'] ?? '');
        if ($code === '') {
            return null;
        }

        $catalog = $this->packagingRepository->getPackagingByCode($code);

        return [
            'c_item' => $code,
            'n_item' => $catalog['n_item'] ?? (string) ($matched['embalaje_desc'] ?? ''),
            'cp2_cajas_por_pallet' => $catalog['cp2_cajas_por_pallet'] ?? null,
            'matched_rule' => $matched,
        ];
    }

    /**
     * Devuelve una lista de opciones sugeridas (por prioridad de matriz).
     *
     * Útil para UI: al asignar embalaje, mostrar 6-12 opciones relevantes sin buscar.
     *
     * @return array<int, array{c_item:string,n_item?:string,cp2_cajas_por_pallet?:int|null,matched_rule?:array}>
     */
    public function suggestOptions(array $inventoryRow, array $qualitySnap = [], int $limit = 12): array
    {
        $matrix = $this->matrixForInventoryRow($inventoryRow);
        $rules = $this->rules($matrix);
        if (empty($rules)) {
            return [];
        }

        $ctx = $this->buildContext($inventoryRow, $qualitySnap);
        if (($ctx['especie'] ?? '') === '') {
            return [];
        }

        $seen = [];
        $picked = [];

        $passes = [
            ['nota' => true, 'variedad' => true, 'color' => true],
            ['nota' => false, 'variedad' => true, 'color' => true],
            ['nota' => false, 'variedad' => false, 'color' => true],
            ['nota' => false, 'variedad' => false, 'color' => false],
        ];

        foreach ($passes as $p) {
            foreach ($rules as $rule) {
                if (! $this->ruleMatchesContext($rule, $ctx, $p)) {
                    continue;
                }

                $code = trim((string) ($rule['embalaje_code'] ?? ''));
                if ($code === '' || isset($seen[$code])) {
                    continue;
                }

                $seen[$code] = true;
                $picked[] = [
                    'c_item' => $code,
                    'matched_rule' => $rule,
                    'desc_embalaje' => (string) ($rule['embalaje_desc'] ?? ''),
                ];

                if (count($picked) >= $limit) {
                    break 2;
                }
            }
        }

        if (empty($picked)) {
            return [];
        }

        $codes = array_values(array_unique(array_map(fn ($r) => (string) $r['c_item'], $picked)));
        $catalog = $this->packagingRepository->getPackagingsByCodes($codes);

        return array_values(array_map(function (array $row) use ($catalog) {
            $c = $catalog[$row['c_item']] ?? null;
            return [
                'c_item' => $row['c_item'],
                'n_item' => $c['n_item'] ?? ($row['desc_embalaje'] ?: null),
                'cp2_cajas_por_pallet' => $c['cp2_cajas_por_pallet'] ?? null,
                'matched_rule' => $row['matched_rule'] ?? null,
            ];
        }, $picked));
    }

    private function buildContext(array $row, array $q): array
    {
        // Especie: normalizamos para tolerar "NECTARIN" vs "Nectarines", etc.
        $especie = $this->normalizeSpecies((string) ($row['especie'] ?? ''));
        $matrix = $this->matrixForSpecies($especie);

        // Destino: en muchas instalaciones NO existe un campo confiable en inventario
        // con valores tipo "CHINA/LATAM/EUROPA/MEXICO". Si no es un destino conocido,
        // lo tratamos como "desconocido" para no bloquear sugerencias.
        $destinoRaw = (string) ($row['destino'] ?? ($row['t_categoria'] ?? ''));
        $destino = $this->normalizeDestino($destinoRaw);

        // Nota: preferimos calidad (MySQL). Si no, usamos la que venga en SQLSRV (Nota_Calidad).
        $notaRaw = (string) ($q['setup_nota_calidad'] ?? ($row['nota_calidad_sqlsrv'] ?? ''));
        $nota = $this->normalizeNota($notaRaw);

        // Variedad: nombre desde inventario (n_variedad).
        $variedad = $this->normalizeKey((string) ($row['variedad'] ?? ''));

        // Color: viene desde Calidad (MySQL) como "COLOR DE CUBRIMIENTO".
        // Si no existe, caemos a campos de inventario (solo como última opción).
        $colorRaw = (string) ($q['setup_color'] ?? ($row['color'] ?? ($row['categoria'] ?? ($row['n_categoria'] ?? ''))));
        $color = $this->normalizeKey($colorRaw);

        // SDP: la matriz tiene casos "… con SDP".
        $hasSdp = trim((string) ($row['sdp_centrocosto'] ?? '')) !== ''
            || ($color !== '' && Str::contains($color, 'SDP'));

        // Calibre:
        // - carozos: número (28,30,32,...)
        // - cherries: etiqueta (L,LD,XL,XLD,J,JD,2J,2JD,...)
        $calibreRaw = (string) ($q['setup_calibre'] ?? ($row['calibre'] ?? ''));
        $calibre = $matrix === 'cherries'
            ? $this->parseCherryCalibreKey($calibreRaw)
            : $this->parseCarozosCalibreKey($calibreRaw);

        $allowedDestinos = [];
        if (isset($q['allowed_destinos']) && is_array($q['allowed_destinos'])) {
            $allowedDestinos = collect($q['allowed_destinos'])
                ->map(fn ($d) => $this->normalizeDestino((string) $d))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [
            'especie' => $especie,
            'matrix' => $matrix,
            'destino' => $destino,
            'allowed_destinos' => $allowedDestinos,
            'nota' => $nota,
            'variedad' => $variedad,
            'color' => $color,
            'has_sdp' => $hasSdp,
            'calibre' => $calibre, // string|null
        ];
    }

    private function findFirstMatch(array $rules, array $ctx): ?array
    {
        // Priorización simple: primero match "estricto"; si no, intentamos relajar Nota/Color/Variedad.
        // Mantiene orden del CSV (más arriba = preferencia).
        $passes = [
            ['nota' => true, 'variedad' => true, 'color' => true],
            ['nota' => false, 'variedad' => true, 'color' => true],
            ['nota' => false, 'variedad' => false, 'color' => true],
            ['nota' => false, 'variedad' => false, 'color' => false],
        ];

        foreach ($passes as $p) {
            foreach ($rules as $rule) {
                if (! $this->ruleMatchesContext($rule, $ctx, $p)) {
                    continue;
                }
                return $rule;
            }
        }

        return null;
    }

    private function ruleMatchesContext(array $rule, array $ctx, array $pass): bool
    {
        if (($rule['especie'] ?? '') !== ($ctx['especie'] ?? '')) {
            return false;
        }

        // Destino:
        // - Si el contexto trae un destino (CHINA/LATAM/...), exigimos match exacto.
        // - Si el contexto NO trae destino (desconocido), NO bloqueamos: mostramos
        //   opciones para todos los destinos de la matriz.
        if (! empty($rule['destino'])) {
            $allowed = $ctx['allowed_destinos'] ?? [];
            if (is_array($allowed) && count($allowed) > 0) {
                if (! in_array((string) $rule['destino'], array_map('strval', $allowed), true)) {
                    return false;
                }
            }

            $ctxDestino = (string) ($ctx['destino'] ?? '');
            if ($ctxDestino !== '' && $rule['destino'] !== $ctxDestino) {
                return false;
            }
        }

        if (($pass['nota'] ?? true) && ! $this->notaMatches($rule['nota'] ?? '', $ctx['nota'] ?? '')) {
            return false;
        }

        if (($pass['variedad'] ?? true) && ! $this->keyMatches($rule['variedad'] ?? '', $ctx['variedad'] ?? '')) {
            return false;
        }

        if (($pass['color'] ?? true) && ! $this->colorMatches($rule['color'] ?? '', $ctx['color'] ?? '', (bool) ($ctx['has_sdp'] ?? false))) {
            return false;
        }

        if (! empty($rule['require_sdp']) && ! (bool) ($ctx['has_sdp'] ?? false)) {
            return false;
        }

        // Calibre: si tenemos número, exige que esté marcado X/x.
        $calibre = $ctx['calibre'] ?? null;
        if ($calibre !== null) {
            $allowed = $rule['allowed_calibres'] ?? [];
            return in_array($calibre, $allowed, true) || in_array((string) $calibre, array_map('strval', $allowed), true);
        }

        // Sin calibre no bloqueamos (pero se sugiere igual).
        return true;
    }

    private function notaMatches(string $ruleNota, string $ctxNota): bool
    {
        $r = $this->normalizeNota($ruleNota);
        if ($r === '' || $r === 'TODAS') {
            return true;
        }
        $c = $this->normalizeNota($ctxNota);
        if ($c === '') {
            return false;
        }
        return $r === $c;
    }

    private function keyMatches(string $ruleValue, string $ctxValue): bool
    {
        $r = $this->normalizeKey($ruleValue);
        if ($r === '') {
            return true;
        }
        $c = $this->normalizeKey($ctxValue);
        if ($c === '') {
            return false;
        }
        return $r === $c;
    }

    private function colorMatches(string $ruleColor, string $ctxColor, bool $hasSdp): bool
    {
        $r = $this->normalizeKey($ruleColor);
        if ($r === '') {
            return true;
        }

        // Si la regla menciona SDP, la exigimos.
        if (Str::contains($r, 'SDP') && ! $hasSdp) {
            return false;
        }

        // Regla puede venir como "BLANCOS / AMARILLOS ..."
        $parts = array_values(array_filter(array_map('trim', preg_split('/\\//', $r) ?: [])));
        if (empty($parts)) {
            $parts = [$r];
        }

        $c = $this->normalizeKey($ctxColor);
        if ($c === '') {
            return false;
        }

        foreach ($parts as $p) {
            // permitimos match por prefijo (ej: "BLANCOS" matchea "BLANCOS CON SDP")
            if ($p !== '' && (Str::startsWith($c, $p) || Str::startsWith($p, $c) || $p === $c)) {
                return true;
            }
        }

        return false;
    }

    private function parseCalibre(string $value): ?int
    {
        $s = trim($value);
        if ($s === '') {
            return null;
        }
        if (preg_match('/(\\d{2,3})/', $s, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function normalizeNota(string $value): string
    {
        $s = $this->normalizeKey($value);
        if ($s === '') {
            return '';
        }
        if (Str::contains($s, 'TODAS')) {
            return 'TODAS';
        }
        if (preg_match('/\\bNOTA\\s*(\\d)\\b/', $s, $m)) {
            return 'NOTA '.$m[1];
        }
        if (preg_match('/\\b(\\d)\\b/', $s, $m)) {
            return 'NOTA '.$m[1];
        }
        if (Str::contains($s, 'PREMIUM')) {
            return 'PREMIUM';
        }
        return $s;
    }

    private function normalizeDestino(string $value): string
    {
        $k = $this->normalizeKey($value);
        if ($k === '') {
            return '';
        }

        // Normalizaciones frecuentes
        $k = str_replace(['E.U.A', 'EUA'], 'USA', $k);
        $k = str_replace(['E E U U', 'E.E.U.U.'], 'EEUU', $k);

        foreach (self::DESTINOS_CONOCIDOS as $known) {
            if ($k === $known || Str::contains($k, $known)) {
                // Asegura valor canónico
                if (Str::contains($known, 'E.E.U.U')) {
                    return 'EEUU';
                }
                return $known === 'E.E.U.U' ? 'EEUU' : $known;
            }
        }

        // Desconocido -> no lo usamos para filtrar reglas.
        return '';
    }

    private function normalizeSpecies(string $value): string
    {
        $k = $this->normalizeKey($value);
        if ($k === '') {
            return '';
        }
        // Alias simples (carozos)
        if (Str::contains($k, 'CHERR') || Str::contains($k, 'CEREZ')) {
            return 'CHERRIES';
        }
        if (Str::contains($k, 'NECTAR')) {
            return 'NECTARINES';
        }
        if (Str::contains($k, 'CIRUEL') || Str::contains($k, 'PLUM')) {
            return 'CIRUELAS';
        }
        return $k;
    }

    private function normalizeKey(string $value): string
    {
        $s = trim($value);
        if ($s === '') {
            return '';
        }
        $s = Str::ascii($s);
        $s = mb_strtoupper($s);
        $s = preg_replace('/\\s+/', ' ', (string) $s);
        return trim((string) $s);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rules(string $matrix): array
    {
        $dbRules = $this->dbRules($matrix);
        if (! empty($dbRules)) {
            return $dbRules;
        }

        // CSV fallback solo para carozos (compatibilidad).
        if ($matrix !== 'carozos') {
            return [];
        }

        $cfg = (array) config('planning.packaging_matrix.carozos', []);
        $storagePath = (string) ($cfg['storage_path'] ?? '');
        $fallbackPath = (string) ($cfg['fallback_path'] ?? '');
        $ttlMinutes = (int) ($cfg['cache_ttl_minutes'] ?? 60);

        $path = $storagePath !== '' && File::exists($storagePath) ? $storagePath : $fallbackPath;
        $path = $path !== '' ? $path : $fallbackPath;
        if ($path === '' || ! File::exists($path)) {
            return [];
        }

        $mtime = (int) File::lastModified($path);
        $cacheKey = 'planning:packaging_matrix:carozos:'.$mtime.':'.md5($path);

        return Cache::remember($cacheKey, now()->addMinutes(max(1, $ttlMinutes)), function () use ($path) {
            $lines = File::lines($path);
            $rows = [];

            foreach ($lines as $line) {
                $raw = trim((string) $line);
                if ($raw === '' || preg_match('/^;+\$/', $raw)) {
                    continue;
                }
                $rows[] = $raw;
            }
            if (count($rows) < 2) {
                return [];
            }

            $header = str_getcsv($rows[0], ';');
            $calibreCols = array_values(array_filter($header, fn ($h) => preg_match('/^\\d{2,3}$/', (string) $h)));

            $rules = [];
            foreach (array_slice($rows, 1) as $line) {
                $data = str_getcsv($line, ';');
                $assoc = [];
                foreach ($header as $idx => $h) {
                    $assoc[(string) $h] = $data[$idx] ?? null;
                }

                $allowed = [];
                foreach ($calibreCols as $c) {
                    $val = trim((string) ($assoc[$c] ?? ''));
                    if ($val !== '' && mb_strtoupper($val) === 'X') {
                        $allowed[] = (string) (int) $c;
                    }
                }

                $rules[] = [
                    'especie' => $this->normalizeSpecies((string) ($assoc['especie'] ?? '')),
                    'destino' => $this->normalizeKey((string) ($assoc['Destino'] ?? '')),
                    'nota' => $this->normalizeNota((string) ($assoc['Nota'] ?? '')),
                    'embalaje_code' => trim((string) ($assoc['Embalaje'] ?? '')),
                    'embalaje_desc' => (string) ($assoc['Desc Embalaje'] ?? ''),
                    'peso_caja' => (string) ($assoc['Peso Cja'] ?? ''),
                    'variedad' => $this->normalizeKey((string) ($assoc['Variedad'] ?? '')),
                    'color' => $this->normalizeKey((string) ($assoc['Color'] ?? '')),
                    'allowed_calibres' => $allowed,
                    'raw' => [
                        'Calibres' => (string) ($assoc['Calibres'] ?? ''),
                        'MIX' => (string) ($assoc['MIX'] ?? ''),
                        'Sobre Calibre' => (string) ($assoc['Sobre Calibre'] ?? ''),
                    ],
                ];
            }

            return $rules;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dbRules(string $matrix): array
    {
        try {
            if (! Schema::hasTable('packaging_matrix_rules')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $updatedAt = PackagingMatrixRule::query()
            ->where('matrix', $matrix)
            ->where('activo', true)
            ->max('updated_at');

        if (! $updatedAt) {
            return [];
        }

        $cacheKey = 'planning:packaging_matrix:'.$matrix.':db:'.md5((string) $updatedAt);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($matrix) {
            $rows = PackagingMatrixRule::query()
                ->where('matrix', $matrix)
                ->where('activo', true)
                ->orderBy('priority')
                ->orderBy('id')
                ->get();

            return $rows->map(function (PackagingMatrixRule $r) {
                $allowed = [];
                if (is_array($r->allowed_calibres)) {
                    $allowed = collect($r->allowed_calibres)
                        ->map(fn ($v) => trim((string) $v))
                        ->filter(fn ($v) => $v !== '')
                        ->unique()
                        ->values()
                        ->all();
                }

                return [
                    'especie' => $this->normalizeSpecies((string) $r->especie),
                    'destino' => $this->normalizeKey((string) ($r->destino ?? '')),
                    'nota' => $this->normalizeNota((string) ($r->nota ?? '')),
                    'embalaje_code' => trim((string) $r->c_item),
                    'embalaje_desc' => (string) ($r->desc_embalaje ?? ''),
                    'peso_caja' => $r->peso_caja !== null ? (string) $r->peso_caja : null,
                    'variedad' => $this->normalizeKey((string) ($r->variedad ?? '')),
                    'color' => $this->normalizeKey((string) ($r->color ?? '')),
                    'require_sdp' => (bool) $r->require_sdp,
                    'allowed_calibres' => $allowed,
                    'raw' => [
                        'Calibres' => (string) ($r->calibres_note ?? ''),
                        'MIX' => '',
                        'Sobre Calibre' => (string) ($r->sobre_calibre_note ?? ''),
                    ],
                ];
            })->values()->all();
        });
    }

    private function matrixForInventoryRow(array $inventoryRow): string
    {
        $species = $this->normalizeSpecies((string) ($inventoryRow['especie'] ?? ''));
        return $this->matrixForSpecies($species);
    }

    private function matrixForSpecies(string $normalizedSpecies): string
    {
        return $normalizedSpecies === 'CHERRIES' ? 'cherries' : 'carozos';
    }

    private function parseCarozosCalibreKey(string $value): ?string
    {
        $n = $this->parseCalibre($value);
        return $n !== null ? (string) $n : null;
    }

    private function parseCherryCalibreKey(string $value): ?string
    {
        $k = $this->normalizeKey($value);
        if ($k === '') {
            return null;
        }
        $k = str_replace(' ', '', $k);
        // Si viene con otros textos, intentamos extraer el patrón principal.
        if (preg_match('/\\b(\\d?J)(D)?\\b/', $k, $m)) {
            $base = $m[1] ?? '';
            $suffix = isset($m[2]) && $m[2] !== '' ? 'D' : '';
            $k = $base.$suffix;
        }

        if (in_array($k, self::CHERRIES_CALIBRES, true)) {
            return $k;
        }

        return $k;
    }
}
