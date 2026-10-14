<?php

namespace App\Services\Garces\PalletBox;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SqlServerReader
{
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

        try {
            if ($folios !== []) {
                $folios = array_values(array_unique(array_map(fn ($f) => trim((string) $f), $folios)));
                $placeholders = implode(',', array_fill(0, count($folios), '?'));

                $rows = DB::connection($this->connection())->select(
                    <<<SQL
                    SELECT TOP 500
                           id_g_produccion,
                           folio,
                           fecha_produccion,
                           c_especie,
                           n_especie,
                           c_variedad,
                           n_variedad,
                           c_variedad_comercial,
                           c_embalaje,
                           CP1,
                           CP2,
                           CP3,
                           CP4,
                           CP5,
                           c_contenedor,
                           c_categoria,
                           n_categoria,
                           t_categoria,
                           c_calibre,
                           c_etiqueta,
                           c_altura,
                           cantidad,
                           peso_neto
                      FROM {$view}
                     WHERE folio IN ({$placeholders})
                     ORDER BY id_g_produccion DESC
                    SQL,
                    $folios
                );
            } else {
                $rows = DB::connection($this->connection())->select(
                    <<<SQL
                    SELECT TOP 500
                           id_g_produccion,
                           folio,
                           fecha_produccion,
                           c_especie,
                           n_especie,
                           c_variedad,
                           n_variedad,
                           c_variedad_comercial,
                           c_embalaje,
                           CP1,
                           CP2,
                           CP3,
                           CP4,
                           CP5,
                           c_contenedor,
                           c_categoria,
                           n_categoria,
                           t_categoria,
                           c_calibre,
                           c_etiqueta,
                           c_altura,
                           cantidad,
                           peso_neto
                      FROM {$view}
                     ORDER BY id_g_produccion DESC
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
                    SELECT s.ncaja,
                           s.id_pkg_stock_det_creacion,
                           d.folio,
                           d.id_adm_p_items,
                           d.id_pro_p_categorias,
                           d.id_pro_p_calibres,
                           d.id_pro_p_etiquetas,
                           d.lote_recepcion,
                           d.recepciones_trazabilidad,
                           d.Programacion_Id_G_Produccion,
                           d.id_adm_p_entidades_productor_rotulacion,
                           d.id_pro_p_variedades_rotulacion,
                           d.LoteR_Unitec,
                           s.Peso_medido,
                           s.Fecha_Hora_Creacion,
                           s.peso_fijo,
                           s.Origen
                      FROM PKG_Stock_Cajas s
                      JOIN PKG_Stock_Det d ON d.id_pkg_stock_det_creacion = s.id_pkg_stock_det_creacion
                     WHERE d.folio IN ({$placeholders})
                    SQL,
                    $folios
                );
            } else {
                $rows = DB::connection($conn)->select(
                    <<<'SQL'
                    SELECT TOP 5000
                           s.ncaja,
                           s.id_pkg_stock_det_creacion,
                           d.folio,
                           d.id_adm_p_items,
                           d.id_pro_p_categorias,
                           d.id_pro_p_calibres,
                           d.id_pro_p_etiquetas,
                           d.lote_recepcion,
                           d.recepciones_trazabilidad,
                           d.Programacion_Id_G_Produccion,
                           d.id_adm_p_entidades_productor_rotulacion,
                           d.id_pro_p_variedades_rotulacion,
                           d.LoteR_Unitec,
                           s.Peso_medido,
                           s.Fecha_Hora_Creacion,
                           s.peso_fijo,
                           s.Origen
                      FROM PKG_Stock_Cajas s
                      JOIN PKG_Stock_Det d ON d.id_pkg_stock_det_creacion = s.id_pkg_stock_det_creacion
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
}
