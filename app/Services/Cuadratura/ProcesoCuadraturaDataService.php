<?php

namespace App\Services\Cuadratura;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcesoCuadraturaDataService
{
    public function getCabecera(int|string $numeroProceso): ?array
    {
        $row = DB::connection('sqlsrv')
            ->table($this->entradasView() . ' as pe')
            ->select([
                'pe.n_productor',
                'pe.n_especie',
                'pe.n_variedad',
                'pe.n_linea_proceso',
                'pe.n_centrocosto',
                'pe.numero_g_produccion',
                'pe.fecha_g_produccion',
                'pe.n_turno',
                'pe.n_tipo_proceso',
                'pe.t_categoria',
                'pe.c_embalaje',
                'pe.n_calibre',
                'pe.n_etiqueta',
            ])
            ->where('pe.numero_g_produccion', (string) $numeroProceso)
            ->first();

        return $row ? (array) $row : null;
    }

    public function getIngresos(int|string $numeroProceso): Collection
    {
        $rows = DB::connection('sqlsrv')
            ->table($this->entradasView() . ' as pe')
            ->selectRaw("
                pe.n_productor,
                CONCAT(pe.ngd_recepcion, '/', pe.numero_guia_recepcion) as guia_lote,
                pe.n_especie,
                pe.n_variedad,
                pe.n_embalaje,
                pe.t_categoria,
                pe.c_embalaje,
                pe.n_calibre,
                pe.n_etiqueta,
                SUM(pe.cantidad) as cantidad,
                SUM(pe.peso_neto) as peso
            ")
            ->where('pe.numero_g_produccion', (string) $numeroProceso)
            ->groupBy([
                'pe.n_productor',
                'pe.n_especie',
                'pe.n_variedad',
                'pe.n_embalaje',
                'pe.t_categoria',
                'pe.numero_guia_recepcion',
                DB::raw("CONCAT(pe.ngd_recepcion, '/', pe.numero_guia_recepcion)"),
                'pe.c_embalaje',
                'pe.n_calibre',
                'pe.n_etiqueta',
            ])
            ->orderBy('pe.n_productor')
            ->get();

        return collect($rows)->map(fn ($row) => [
            'n_productor' => $row->n_productor,
            'guia_lote' => $row->guia_lote,
            'n_especie' => $row->n_especie,
            'n_variedad' => $row->n_variedad,
            'n_embalaje' => $row->n_embalaje,
            't_categoria' => $row->t_categoria,
            'c_embalaje' => $row->c_embalaje,
            'n_calibre' => $row->n_calibre,
            'n_etiqueta' => $row->n_etiqueta,
            'cantidad' => (float) $row->cantidad,
            'peso' => (float) $row->peso,
        ]);
    }

    public function getSalidas(int|string $numeroProceso, ?int $idEmpresa = null): Collection
    {
        $query = DB::connection('sqlsrv')
            ->table($this->completoView() . ' as pc')
            ->selectRaw('
                pc.n_productor,
                pc.n_especie,
                pc.n_variedad,
                pc.c_embalaje,
                pc.n_embalaje,
                pc.n_categoria,
                pc.t_categoria,
                pc.n_etiqueta,
                pc.n_calibre,
                SUM(pc.cantidad) as cantidad,
                SUM(pc.peso_neto) as peso_neto
            ')
            ->where('pc.t_categoria', '!=', 'Sin Procesar')
            ->where('pc.numero_proceso', (string) $numeroProceso);

        if ($idEmpresa !== null) {
            $query->where('pc.id_empresa', $idEmpresa);
        }

        $rows = $query
            ->groupBy([
                'pc.n_productor',
                'pc.n_especie',
                'pc.n_variedad',
                'pc.c_embalaje',
                'pc.n_embalaje',
                'pc.n_categoria',
                'pc.t_categoria',
                'pc.n_etiqueta',
                'pc.n_calibre',
            ])
            ->orderBy('pc.n_productor')
            ->get();

        return collect($rows)->map(fn ($row) => [
            'n_productor' => $row->n_productor,
            'n_especie' => $row->n_especie,
            'n_variedad' => $row->n_variedad,
            'c_embalaje' => $row->c_embalaje,
            'n_embalaje' => $row->n_embalaje,
            'n_categoria' => $row->n_categoria,
            't_categoria' => $row->t_categoria,
            'n_calibre' => $row->n_calibre,
            'n_etiqueta' => $row->n_etiqueta,
            'cantidad' => (float) $row->cantidad,
            'peso_neto' => (float) $row->peso_neto,
        ]);
    }

    private function entradasView(): string
    {
        return (string) config('cuadratura.sqlsrv.views.entradas', 'V_PKG_Produccion_Entradas_XXX');
    }

    private function completoView(): string
    {
        return (string) config('cuadratura.sqlsrv.views.completo', 'V_PKG_Produccion_Completo_XXX');
    }
}
