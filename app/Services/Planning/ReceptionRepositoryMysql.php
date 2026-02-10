<?php

namespace App\Services\Planning;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReceptionRepositoryMysql
{
    /**
     * Kilos recepcionados por día / productor / especie / variedad.
     *
     * Importante:
     * - `recepcions.id_emisor` se relaciona con `users.idprod` (productor).
     * - `fecha_g_recepcion` se usa como fecha operativa de recepción.
     *
     * Retorna Collection<array{dia, producer_id, producer_name, especie, variedad, kilos}>
     */
    public function getDailyKilos(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $query = DB::table('recepcions as r')
            ->join('users as u', 'u.idprod', '=', 'r.id_emisor')
            ->whereNotNull('r.fecha_g_recepcion')
            ->whereBetween(DB::raw('date(r.fecha_g_recepcion)'), [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['only_active_producers'])) {
            $query->where('u.is_active', true);
        }

        if (! empty($filters['especie'])) {
            $needle = mb_strtolower(trim((string) $filters['especie']));
            $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
            $needle = preg_replace('/\s+/', ' ', (string) $needle);
            $needle = trim((string) $needle);
            $needle = preg_replace('/s$/', '', (string) $needle);
            $needle = mb_substr((string) $needle, 0, 7);
            $query->whereRaw('lower(ltrim(rtrim(coalesce(r.n_especie, \'\')))) like ?', ['%'.$needle.'%']);
        }

        if (! empty($filters['variedad'])) {
            $needle = mb_strtolower(trim((string) $filters['variedad']));
            $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
            $needle = preg_replace('/\s+/', ' ', (string) $needle);
            $needle = trim((string) $needle);
            $needle = mb_substr((string) $needle, 0, 12);
            $query->whereRaw('lower(ltrim(rtrim(coalesce(r.n_variedad, \'\')))) like ?', ['%'.$needle.'%']);
        }

        if (! empty($filters['producer_q'])) {
            $needle = '%'.mb_strtolower(trim((string) $filters['producer_q'])).'%';
            $query->whereRaw('lower(coalesce(u.name, \'\')) like ?', [$needle]);
        }

        $rows = $query
            ->selectRaw('
                date(r.fecha_g_recepcion) as dia,
                u.id as producer_id,
                max(u.name) as producer_name,
                max(r.n_especie) as especie,
                max(r.n_variedad) as variedad,
                sum(coalesce(r.peso_neto,0)) as kilos
            ')
            ->groupBy(DB::raw('date(r.fecha_g_recepcion)'), 'u.id')
            ->groupBy('r.n_especie', 'r.n_variedad')
            ->orderBy('dia')
            ->orderBy('producer_name')
            ->get();

        return collect($rows)->map(fn ($row) => [
            'dia' => (string) $row->dia,
            'producer_id' => (int) $row->producer_id,
            'producer_name' => (string) ($row->producer_name ?? ''),
            'especie' => $row->especie !== null ? (string) $row->especie : null,
            'variedad' => $row->variedad !== null ? (string) $row->variedad : null,
            'kilos' => (float) $row->kilos,
        ]);
    }
}
