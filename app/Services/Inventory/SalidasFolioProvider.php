<?php

namespace App\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalidasFolioProvider
{
    public function latest(int $limit = 200): Collection
    {
        $view = $this->viewName();

        try {
            $rows = DB::connection($this->connection())
                ->select(
                    <<<SQL
                    SELECT id_g_produccion,
                           folio,
                           numero_g_produccion,
                           c_embalaje,
                           n_embalaje,
                           n_linea_proceso,
                           n_turno,
                           n_calibre,
                           n_especie,
                           n_variedad,
                           CAST(fecha_produccion AS date) AS fecha_produccion,
                           SUM(CAST(cantidad AS decimal(18, 4))) AS cantidad,
                           SUM(CAST(peso_neto AS decimal(18, 4))) AS peso_neto
                      FROM {$view}
                     WHERE folio NOT LIKE '%C'
                     GROUP BY id_g_produccion, folio, numero_g_produccion, c_embalaje, n_embalaje,
                              n_linea_proceso, n_turno, n_calibre, n_especie, n_variedad,
                              CAST(fecha_produccion AS date)
                     ORDER BY MAX(id_g_produccion) DESC
                    SQL
                );
        } catch (\Throwable $e) {
            Log::error('AUTO_CONSUMPTION_SQLSRV_ERROR', [
                'error' => $e->getMessage(),
                'view' => $view,
            ]);

            return collect();
        }

        return collect($rows)
            ->filter(fn ($row) => ! empty($row->folio) && ! empty($row->c_embalaje))
            ->take($limit)
            ->values();
    }

    public function connection(): string
    {
        return (string) config('services.termo.auto_consumption.conexion', 'sqlsrv');
    }

    public function viewName(): string
    {
        return (string) config('services.termo.sqlsrv_view', 'V_PKG_Produccion_Salidas_XXX');
    }

    /**
     * Busca folios de producción en la vista TERMO por id_g_produccion,
     * preservando el orden de los ids solicitados. Devuelve solo los encontrados.
     *
     * @param  list<int>  $ids
     */
    public function byIds(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return collect();
        }

        $view = $this->viewName();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $rows = DB::connection($this->connection())
                ->select(
                    <<<SQL
                    SELECT id_g_produccion,
                           folio,
                           c_embalaje,
                           n_embalaje,
                           n_linea_proceso,
                           n_turno,
                           n_calibre,
                           n_especie,
                           n_variedad,
                           MAX(CAST(fecha_produccion AS date)) AS fecha_produccion,
                           SUM(CAST(cantidad AS decimal(18, 4))) AS cantidad,
                           SUM(CAST(peso_neto AS decimal(18, 4))) AS peso_neto
                      FROM {$view}
                     WHERE folio NOT LIKE '%C'
                       AND id_g_produccion IN ({$placeholders})
                     GROUP BY id_g_produccion, folio, c_embalaje, n_embalaje,
                              n_linea_proceso, n_turno, n_calibre, n_especie, n_variedad
                    SQL, $ids
                );
        } catch (\Throwable $e) {
            Log::error('AUTO_CONSUMPTION_SQLSRV_ERROR', [
                'error' => $e->getMessage(),
                'view' => $view,
            ]);

            return collect();
        }

        $byId = collect($rows)->keyBy('id_g_produccion');

        return collect($ids)
            ->map(fn (int $id) => $byId->get($id))
            ->filter()
            ->values();
    }
}