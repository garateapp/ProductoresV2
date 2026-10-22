<?php

namespace App\Services\Garces\PalletBox;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SqlServerReader
{
    /**
     * Columnas deseadas de la vista de cabeceras (inventario/resumen).
     * Se cruzan con las que realmente existen en la vista configurada.
     */
    private const CABECERA_COLUMNS = [
        'id_g_produccion',
        'folio',
        'fecha_produccion',
        'id_especie',
        'n_especie',
        'id_variedad',
        'n_variedad',
        'id_embalaje',
        'c_embalaje',
        'n_embalaje',

        'id_contenedor',
        'c_contenedor',
        'n_contenedor',
        'id_categoria',
        'c_categoria',
        'n_categoria',
        't_categoria',

        'id_etiqueta',
        'c_etiqueta',
        'n_etiqueta',
        'id_altura',
        'cantidad',
        'peso_neto',

        'id_exportadora',
        'c_exportadora',
        'n_exportadora',
    ];

    public function connection(): string
    {
        return (string) config('services.termo.auto_consumption.conexion', 'sqlsrv');
    }

    public function viewCabecera(): string
    {
        return (string) config('services.termo.sqlsrv_view', 'V_PKG_Produccion_Salidas_XXX');
    }

    /**
     * @return Collection<int,object>
     */
    public function getCabeceras(array $folios = []): Collection
    {
        $view = $this->viewCabecera();
        $columns = $this->availableColumns($view, self::CABECERA_COLUMNS);

        if (! in_array('folio', $columns, true)) {
            Log::error('GARCES_PALLETBOX_SQLSRV_CAB', [
                'error' => 'La vista no expone la columna folio.',
                'view' => $view,
            ]);

            return collect();
        }

        $groupBy = [];
        foreach ($columns as $col) {
            if (in_array($col, ['cantidad', 'peso_neto'], true)) {
                continue;
            }
            $groupBy[] = $col;
        }

        $aggSelect = implode(",\n                           ", array_map(function ($col) use ($columns) {
            if ($col === 'cantidad') {
                return 'SUM(cantidad) AS cantidad';
            }
            if ($col === 'peso_neto') {
                return 'SUM(peso_neto) AS peso_neto';
            }

            return $col;
        }, $columns));

        $groupByClause = implode(', ', $groupBy);

        try {
            if ($folios !== []) {
                $folios = array_values(array_unique(array_map(fn ($f) => trim((string) $f), $folios)));
                $placeholders = implode(',', array_fill(0, count($folios), '?'));

                $rows = DB::connection($this->connection())->select(
                    <<<SQL
                    SELECT TOP 500
                           $aggSelect
                      FROM {$view}
                     WHERE folio IN ({$placeholders})
                     GROUP BY $groupByClause
                     ORDER BY MAX(id_g_produccion) DESC
                    SQL,
                    $folios
                );
            } else {
                $rows = DB::connection($this->connection())->select(
                    <<<SQL
                    SELECT TOP 500
                           $aggSelect
                      FROM {$view}
                     GROUP BY $groupByClause
                     ORDER BY MAX(id_g_produccion) DESC
                    SQL
                );
            }
        } catch (\Throwable $e) {
            Log::error('GARCES_PALLETBOX_SQLSRV_CAB', [
                'error' => $e->getMessage(),
                'view' => $view,
            ]);

            return collect();
        }

        return collect($rows)->filter(fn ($r) => ! empty($r->folio))->values();
    }

    /**
     * @return Collection<int,object>
     */
    public function getDetalles(array $folios = []): Collection
    {
        $conn = $this->connection();

        try {
            if ($folios !== []) {
                $folios = array_values(array_unique(array_map(fn ($f) => trim((string) $f), $folios)));
                $placeholders = implode(',', array_fill(0, count($folios), '?'));

                $rows = DB::connection($conn)->select(
                    <<<SQL
                    SELECT
                           c.ncaja AS idCaja,
                           d.n_g_proceso AS numeroProceso,
                           d.recepciones_trazabilidad AS lote,
                           d.c_productor_original AS productorReal,
                           d.c_productor AS productor,
                           d.id_variedad AS variedadReal,
                           d.id_variedad AS variedadComercial,
                           d.fecha_produccion AS fechaEmbalaje,
                           d.c_calibre AS calibre,
                           1 AS cajas,
                           d.peso_std_embalaje AS kilos,
                           '' AS clasificacion,
                           d.id_categoria AS categoria,
                           d.id_etiqueta AS etiqueta,
                           d.folio
                      FROM V_PKG_Cajas AS c
                      INNER JOIN V_PKG_Stock_Inventario AS d ON c.id_pkg_stock_det = d.id_pkg_stock_det
                     WHERE c.folio IN ({$placeholders})
                    SQL,
                    $folios
                );
            } else {
                $rows = DB::connection($conn)->select(
                    <<<'SQL'
                    SELECT
                           c.ncaja AS idCaja,
                           d.n_g_proceso AS numeroProceso,
                           d.recepciones_trazabilidad AS lote,
                           d.c_productor_original AS productorReal,
                           d.c_productor AS productor,
                           d.id_variedad AS variedadReal,
                           d.id_variedad AS variedadComercial,
                           d.fecha_produccion AS fechaEmbalaje,
                           d.c_calibre AS calibre,
                           1 AS cajas,
                           d.peso_std_embalaje AS kilos,
                           '' AS clasificacion,
                           d.id_categoria AS categoria,
                           d.id_etiqueta AS etiqueta,
                           c.folio
                      FROM V_PKG_Cajas AS c
                      INNER JOIN V_PKG_Stock_Inventario AS d ON c.id_pkg_stock_det = d.id_pkg_stock_det
                    SQL
                );
            }
        } catch (\Throwable $e) {
            Log::error('GARCES_PALLETBOX_SQLSRV_DET', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        return collect($rows)->filter(fn ($r) => ! empty($r->folio))->values();
    }

    /**
     * Columnas de $wanted que existen realmente en la tabla/vista indicada.
     */
    private function availableColumns(string $table, array $wanted): array
    {
        try {
            $rows = DB::connection($this->connection())->select(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?',
                [$table]
            );
        } catch (\Throwable $e) {
            Log::warning('GARCES_PALLETBOX_SQLSRV_COLUMNS', [
                'error' => $e->getMessage(),
                'table' => $table,
            ]);

            return $wanted;
        }

        $available = array_map(fn ($r) => (string) $r->COLUMN_NAME, $rows);

        if ($available === []) {
            Log::warning('GARCES_PALLETBOX_SQLSRV_COLUMNS', [
                'error' => 'No se pudieron resolver columnas.',
                'table' => $table,
            ]);

            return $wanted;
        }

        $missing = array_values(array_diff($wanted, $available));

        if ($missing !== []) {
            Log::warning('GARCES_PALLETBOX_SQLSRV_COLUMNS_FALTANTES', [
                'table' => $table,
                'missing' => $missing,
            ]);
        }

        return array_values(array_intersect($wanted, $available));
    }
}
