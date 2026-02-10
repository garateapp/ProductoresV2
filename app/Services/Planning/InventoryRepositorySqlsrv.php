<?php

namespace App\Services\Planning;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryRepositorySqlsrv
{
    /**
     * Convierte campos fecha que a veces vienen como NVARCHAR en la vista (con formatos mixtos)
     * a DATE de forma segura (sin reventar por valores inválidos).
     *
     * Nota: evitamos TRY_CONVERT porque hay instalaciones SQL Server antiguas donde no existe.
     */
    private function dateExpr(string $column): string
    {
        $col = trim($column);
        // Formatos comunes en vistas:
        // - 2026-02-15
        // - 2026-02-15 13:45:00
        // - 15/02/2026
        // - 20260215
        //
        // Importante: usamos patrones + CONVERT con estilo para evitar errores de conversión.
        return "case
            when {$col} is null then null
            when ltrim(rtrim(cast({$col} as varchar(50)))) = '' then null
            when ltrim(rtrim(cast({$col} as varchar(50)))) like '[12][0-9][0-9][0-9]-[01][0-9]-[0-3][0-9]%' then convert(date, left(ltrim(rtrim(cast({$col} as varchar(50)))), 10), 23)
            when ltrim(rtrim(cast({$col} as varchar(50)))) like '[0-3][0-9]/[01][0-9]/[12][0-9][0-9][0-9]%' then convert(date, left(ltrim(rtrim(cast({$col} as varchar(50)))), 10), 103)
            when ltrim(rtrim(cast({$col} as varchar(50)))) like '[12][0-9][0-9][0-9][01][0-9][0-3][0-9]%' then convert(date, left(ltrim(rtrim(cast({$col} as varchar(50)))), 8), 112)
            when isdate({$col}) = 1 then convert(date, {$col})
            else null
        end";
    }

    /**
     * Inventario disponible en SQL Server (SOLO LECTURA).
     *
     * Importante:
     * - `n_g_recepcion` es la clave estable para el join con MySQL (Recepcion.numero_g_recepcion).
     * - Esta vista usa nombres como `n_especie`, `n_variedad`, etc (no `especie`/`variedad`).
     * - Algunas existencias NO vienen en bins 1:1: se convierten con `planning.inventory_bin_divisors`.
     */
    public function getAvailableLots(array $filters = []): Collection
    {
        $limit = (int) ($filters['limit'] ?? 200);

        $query = DB::connection('sqlsrv')
            ->table('V_PKG_Stock_Inventario')
            ->where('id_empresa', (int) ($filters['id_empresa'] ?? 1))
            ->where('creacion_tipo', (string) ($filters['creacion_tipo'] ?? 'RFG'));

        $fechaRecepcionExpr = $this->dateExpr('fecha_recepcion');

        if (! empty($filters['exclude_n_g_recepcion']) && is_array($filters['exclude_n_g_recepcion'])) {
            $exclude = collect($filters['exclude_n_g_recepcion'])
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();
            if (! empty($exclude)) {
                $query->whereNotIn('n_g_recepcion', $exclude);
            }
        }

        if (! empty($filters['n_g_recepcion'])) {
            $query->where('n_g_recepcion', trim((string) $filters['n_g_recepcion']));
        }

        if (! empty($filters['especie'])) {
            // En esta vista el campo es `n_especie` (no `especie`)
            $query->where('n_especie', (string) $filters['especie']);
        }

        if (! empty($filters['variedad'])) {
            // En esta vista el campo es `n_variedad` (no `variedad`)
            $query->where('n_variedad', (string) $filters['variedad']);
        }

        if (! empty($filters['q'])) {
            $q = (string) $filters['q'];
            $query->where('n_g_recepcion', 'like', '%'.$q.'%');
        }

        if (! empty($filters['fecha_from'])) {
            $query->whereRaw($fechaRecepcionExpr.' >= ?', [(string) $filters['fecha_from']]);
        }
        if (! empty($filters['fecha_to'])) {
            $query->whereRaw($fechaRecepcionExpr.' <= ?', [(string) $filters['fecha_to']]);
        }

        // Filtros opcionales (si existen en tu vista)
        if (! empty($filters['bodega'])) {
            $query->where('n_bodega', (string) $filters['bodega']);
        }
        if (! empty($filters['productor'])) {
            $query->where('n_productor', (string) $filters['productor']);
        }
        if (! empty($filters['productor_q']) || ! empty($filters['producer_q'])) {
            $needle = (string) ($filters['productor_q'] ?? $filters['producer_q']);
            $query->where('n_productor', 'like', '%'.$needle.'%');
        }

        $cantidadBinsExpr = $this->buildCantidadBinsExpression();
        $fechaCosechaExpr = $this->dateExpr('fecha_cosecha');
        $fechaCosechaSfExpr = $this->dateExpr('fecha_cosecha_sf');

        // Agregación por lote/recepción (n_g_recepcion).
        // Devolvemos campos extra para visualización sin romper las claves normalizadas usadas en UI/motor.
        $rows = $query->selectRaw('
                n_g_recepcion,

                min('.$fechaRecepcionExpr.') as fecha_recepcion,
                min('.$fechaCosechaExpr.') as fecha_cosecha,
                min('.$fechaCosechaSfExpr.') as fecha_cosecha_sf,
                max(antiguedad_cosecha) as antiguedad,
                max(Antiguedad_Cosecha) as antiguedad_cosecha,
                max(Antiguedad_Recepcion) as antiguedad_recepcion,
                max(Antiguedad_Produccion) as antiguedad_produccion,
                max(antiguedad) as antiguedad_general,

                max(descripcion_tipo) as descripcion_tipo,
                max(creacion_id) as creacion_id,
                max(creacion_numero) as creacion_numero,
                max(n_altura) as n_altura,

                max(c_centrocosto) as c_centrocosto,
                max(n_centrocosto) as n_centrocosto,
                max(sdp_centrocosto) as sdp_centrocosto,

                max(c_embalaje) as c_embalaje,
                max(n_embalaje) as n_embalaje,

                max(c_categoria) as c_categoria,
                max(n_categoria) as n_categoria,
                max(t_categoria) as t_categoria,

                max(c_calibre) as c_calibre,
                max(n_calibre) as n_calibre,
                max(CP1_Calibre) as cp1_calibre,

                sum(cantidad) as cantidad,
                '.$cantidadBinsExpr.' as cantidad_bins,
                sum(peso_neto) as peso_neto,

                max(id_productor) as id_productor,
                max(r_productor) as r_productor,
                max(c_productor) as c_productor,
                max(n_productor) as n_productor,
                max(ns_productor) as ns_productor,
                max(CSG_Productor) as csg_productor,

                max(id_especie) as id_especie,
                max(n_especie) as n_especie,
                max(id_variedad) as id_variedad,
                max(n_variedad) as n_variedad,
                max(n_variedad_original) as n_variedad_original,

                max(n_bodega) as n_bodega,
                max(Numero_guia) as numero_guia,
                max(c_productor_original) as c_productor_original,
                max(n_productor_original) as n_productor_original,

                max(notas_recepcion) as notas_recepcion,
                max(referencias_recepcion) as referencias_recepcion,

                max(Nota_Calidad) as nota_calidad_sqlsrv,
                max(Tratamiento) as tratamiento,
                max(texto_libre_hs) as texto_libre_hs,

                max(id_Tratamiento) as id_tratamiento,
                max(C_Tratamiento) as c_tratamiento,
                max(N_Tratamiento) as n_tratamiento,

                max(fecha_validacion) as fecha_validacion,
                max(Estado_Recepcion) as estado_recepcion
            ')
            ->groupBy('n_g_recepcion')
            ->orderByDesc('antiguedad')
            ->orderBy('fecha_recepcion')
            ->limit($limit)
            ->get();

        return collect($rows)->map(function ($row) {
            $cantidadBinsRaw = isset($row->cantidad_bins) ? (float) $row->cantidad_bins : 0.0;
            $cantidadBins = (int) ceil($cantidadBinsRaw);

            return [
                'n_g_recepcion' => (string) ($row->n_g_recepcion ?? ''),
                'fecha_recepcion' => $row->fecha_recepcion,

                // Compatibilidad con claves ya usadas en UI/motor
                'antiguedad' => isset($row->antiguedad) ? (int) $row->antiguedad : null,
                'especie' => $row->n_especie ?? null,
                'variedad' => $row->n_variedad ?? null,
                'calibre' => $row->n_calibre ?? null,
                'categoria' => $row->n_categoria ?? null,
                'productor' => $row->n_productor ?? null,
                'bodega' => $row->n_bodega ?? null,

                // bins calculados (con regla de conversión por embalaje)
                'cantidad_bins' => $cantidadBins,
                'cantidad_bins_raw' => $cantidadBinsRaw,
                'peso_neto' => isset($row->peso_neto) ? (float) $row->peso_neto : null,

                // Campos extra para visualización
                'descripcion_tipo' => $row->descripcion_tipo ?? null,
                'creacion_id' => $row->creacion_id ?? null,
                'creacion_numero' => $row->creacion_numero ?? null,
                'n_altura' => $row->n_altura ?? null,
                'fecha_cosecha' => $row->fecha_cosecha ?? null,
                'fecha_cosecha_sf' => $row->fecha_cosecha_sf ?? null,

                'c_centrocosto' => $row->c_centrocosto ?? null,
                'n_centrocosto' => $row->n_centrocosto ?? null,
                'sdp_centrocosto' => $row->sdp_centrocosto ?? null,

                'c_embalaje' => $row->c_embalaje ?? null,
                'n_embalaje' => $row->n_embalaje ?? null,

                'c_categoria' => $row->c_categoria ?? null,
                'n_categoria' => $row->n_categoria ?? null,
                't_categoria' => $row->t_categoria ?? null,

                'c_calibre' => $row->c_calibre ?? null,
                'n_calibre' => $row->n_calibre ?? null,
                'cp1_calibre' => $row->cp1_calibre ?? null,

                'cantidad' => isset($row->cantidad) ? (float) $row->cantidad : null,

                'id_productor' => $row->id_productor ?? null,
                'r_productor' => $row->r_productor ?? null,
                'c_productor' => $row->c_productor ?? null,
                'n_productor' => $row->n_productor ?? null,
                'ns_productor' => $row->ns_productor ?? null,
                'csg_productor' => $row->csg_productor ?? null,

                'id_especie' => $row->id_especie ?? null,
                'n_especie' => $row->n_especie ?? null,
                'id_variedad' => $row->id_variedad ?? null,
                'n_variedad' => $row->n_variedad ?? null,
                'n_variedad_original' => $row->n_variedad_original ?? null,

                'n_bodega' => $row->n_bodega ?? null,
                'numero_guia' => $row->numero_guia ?? null,
                'c_productor_original' => $row->c_productor_original ?? null,
                'n_productor_original' => $row->n_productor_original ?? null,

                'notas_recepcion' => $row->notas_recepcion ?? null,
                'referencias_recepcion' => $row->referencias_recepcion ?? null,

                'nota_calidad_sqlsrv' => $row->nota_calidad_sqlsrv ?? null,
                'tratamiento' => $row->tratamiento ?? null,
                'texto_libre_hs' => $row->texto_libre_hs ?? null,

                'antiguedad_cosecha' => isset($row->antiguedad_cosecha) ? (int) $row->antiguedad_cosecha : null,
                'antiguedad_recepcion' => isset($row->antiguedad_recepcion) ? (int) $row->antiguedad_recepcion : null,
                'antiguedad_produccion' => isset($row->antiguedad_produccion) ? (int) $row->antiguedad_produccion : null,
                'antiguedad_general' => isset($row->antiguedad_general) ? (int) $row->antiguedad_general : null,

                'id_tratamiento' => $row->id_tratamiento ?? null,
                'c_tratamiento' => $row->c_tratamiento ?? null,
                'n_tratamiento' => $row->n_tratamiento ?? null,

                'fecha_validacion' => $row->fecha_validacion ?? null,
                'estado_recepcion' => $row->estado_recepcion ?? null,
            ];
        })->filter(fn ($row) => $row['n_g_recepcion'] !== '');
    }

    /**
     * Resumen de stock (existencias) por especie/variedad.
     *
     * A diferencia de `getAvailableLots()`, este método NO limita resultados y está pensado para
     * alimentar pantallas resumen (flujo de fruta). Mantener filtros acotados (idealmente por especie).
     *
     * Retorna: Collection de arrays:
     * - especie, variedad
     * - cantidad (float)
     * - cantidad_bins (float)
     * - peso_neto (float)
     */
    public function getStockSummary(array $filters = []): Collection
    {
        $query = DB::connection('sqlsrv')
            ->table('V_PKG_Stock_Inventario')
            ->where('id_empresa', (int) ($filters['id_empresa'] ?? 1))
            ->where('creacion_tipo', (string) ($filters['creacion_tipo'] ?? 'RFG'));

        if (! empty($filters['especie'])) {
            $query->where('n_especie', (string) $filters['especie']);
        }
        if (! empty($filters['variedad'])) {
            $query->where('n_variedad', (string) $filters['variedad']);
        }

        $cantidadBinsExpr = $this->buildCantidadBinsExpression();

        $rows = $query
            ->selectRaw('
                max(n_especie) as especie,
                max(n_variedad) as variedad,
                sum(cast(cantidad as float)) as cantidad,
                '.$cantidadBinsExpr.' as cantidad_bins,
                sum(cast(peso_neto as float)) as peso_neto
            ')
            ->groupBy('n_especie', 'n_variedad')
            ->orderBy('n_especie')
            ->orderBy('n_variedad')
            ->get();

        return collect($rows)->map(fn ($row) => [
            'especie' => $row->especie !== null ? (string) $row->especie : null,
            'variedad' => $row->variedad !== null ? (string) $row->variedad : null,
            'cantidad' => (float) ($row->cantidad ?? 0),
            'cantidad_bins' => (float) ($row->cantidad_bins ?? 0),
            'peso_neto' => (float) ($row->peso_neto ?? 0),
        ]);
    }

    private function buildCantidadBinsExpression(): string
    {
        // sum(CASE ...) con divisor por n_embalaje para convertir unidades a bins.
        $divisors = (array) config('planning.inventory_bin_divisors', []);

        $cases = [];
        foreach ($divisors as $needle => $divisor) {
            $needle = trim((string) $needle);
            $divisor = (float) $divisor;
            if ($needle === '' || $divisor <= 0) {
                continue;
            }

            $escapedNeedle = str_replace("'", "''", mb_strtoupper($needle));
            $cases[] = "when upper(isnull(n_embalaje,'')) like '%{$escapedNeedle}%' then cast(cantidad as float)/{$divisor}";
        }

        $caseSql = 'case '.implode(' ', $cases).' else cast(cantidad as float) end';
        return 'sum('.$caseSql.')';
    }
}
