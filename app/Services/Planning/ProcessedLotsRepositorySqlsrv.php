<?php

namespace App\Services\Planning;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class ProcessedLotsRepositorySqlsrv
{
    /**
     * Kilos procesados (producción) por día / productor / especie / variedad.
     *
     * Fuente: SQLSRV `V_PKG_Produccion_Completo` (ejemplos en AdminDashboard/ProcesoController).
     *
     * Retorna Collection<array{dia, c_productor, producer_name, especie, variedad, kilos}>
     */
    public function getDailyKilos(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $query = DB::connection('sqlsrv')
            ->table('V_PKG_Produccion_Completo_XXX as ppc')
            ->where('ppc.tipo_proceso', 'PRN')
            //->where('ppc.Estado', 'Finalizado')
            //->where('ppc.peso_neto', '>', 0)
            ->where('ppc.t_categoria', '=', 'Sin Procesar')
            ->whereBetween(DB::raw('cast(ppc.fecha_proceso as date)'), [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['especie'])) {
            $needle = mb_strtolower(trim((string) $filters['especie']));
            $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
            $needle = preg_replace('/\s+/', ' ', (string) $needle);
            $needle = trim((string) $needle);
            $needle = preg_replace('/s$/', '', (string) $needle);
            $needle = mb_substr((string) $needle, 0, 7);
            $query->whereRaw('lower(ltrim(rtrim(coalesce(ppc.n_especie_proceso, \'\')))) like ?', ['%'.$needle.'%']);
        }
        if (! empty($filters['variedad'])) {
            $needle = mb_strtolower(trim((string) $filters['variedad']));
            $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
            $needle = preg_replace('/\s+/', ' ', (string) $needle);
            $needle = trim((string) $needle);
            $needle = mb_substr((string) $needle, 0, 12);
            $query->whereRaw('lower(ltrim(rtrim(coalesce(ppc.n_variedad_proceso, \'\')))) like ?', ['%'.$needle.'%']);
        }

        $rows = $query
            ->selectRaw('
                cast(ppc.fecha_proceso as date) as dia,
                max(ppc.c_productor_proceso) as c_productor,
                max(ppc.n_productor_proceso) as producer_name,
                max(ppc.n_especie_proceso) as especie,
                max(ppc.n_variedad_proceso) as variedad,
                sum(cast(ppc.peso_neto as float)) as kilos
            ')
            ->groupBy(DB::raw('cast(ppc.fecha_proceso as date)'), 'ppc.c_productor_proceso', 'ppc.n_productor_proceso', 'ppc.n_especie_proceso', 'ppc.n_variedad_proceso')
            ->orderBy('dia')
            ->get();

        return collect($rows)->map(fn ($row) => [
            'dia' => (string) $row->dia,
            'c_productor' => $row->c_productor !== null ? (string) $row->c_productor : null,
            'producer_name' => $row->producer_name !== null ? (string) $row->producer_name : null,
            'especie' => $row->especie !== null ? (string) $row->especie : null,
            'variedad' => $row->variedad !== null ? (string) $row->variedad : null,
            'kilos' => (float) $row->kilos,
        ]);
    }
}
