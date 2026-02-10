<?php

namespace App\Services\Planning;

use App\Enums\EstimationVersionStatus;
use App\Models\EstimationBiweeklyVersion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BiweeklyEstimationRepositoryMysql
{
    public function getActiveVersionId(int $seasonId): ?int
    {
        return EstimationBiweeklyVersion::query()
            ->where('season_id', $seasonId)
            ->where('status', EstimationVersionStatus::ACTIVE->value)
            ->orderByDesc('created_at')
            ->value('id');
    }

    /**
     * Devuelve la última versión ACTIVE aplicable por semana (periodo bisemanal).
     *
     * Importante: en este proyecto puede haber múltiples versiones ACTIVE simultáneas,
     * una por cada bisemanal (period_start_week/period_end_week).
     *
     * @param  array<int>  $weekNumbers  semanas (1..53)
     * @return array<int,int|null> map semana => version_id
     */
    public function getActiveVersionIdsForWeeks(int $seasonId, array $weekNumbers): array
    {
        $weeks = collect($weekNumbers)
            ->map(fn ($w) => (int) $w)
            ->filter(fn ($w) => $w >= 1 && $w <= 53)
            ->unique()
            ->values();

        if ($weeks->isEmpty()) {
            return [];
        }

        $actives = EstimationBiweeklyVersion::query()
            ->where('season_id', $seasonId)
            ->where('status', EstimationVersionStatus::ACTIVE->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'period_start_week', 'period_end_week', 'created_at']);

        $fallback = $actives->first()
            ?: EstimationBiweeklyVersion::query()
                ->where('season_id', $seasonId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first(['id']);

        $map = [];
        foreach ($weeks as $week) {
            $picked = null;
            foreach ($actives as $v) {
                $start = (int) ($v->period_start_week ?? 1);
                $end = (int) ($v->period_end_week ?? 53);
                if ($week >= $start && $week <= $end) {
                    $picked = (int) $v->id;
                    break; // ya viene en orderBy desc => el primero es el más reciente
                }
            }
            $map[$week] = $picked ?? ($fallback ? (int) $fallback->id : null);
        }

        return $map;
    }

    /**
     * Devuelve kilos estimados por día (y opcionalmente por variedad) usando la versión ACTIVE más reciente
     * de la temporada indicada.
     *
     * Retorna: Collection de arrays
     * - dia (Y-m-d)
     * - especie
     * - variedad (nombre) (nullable)
     * - total_kilo (float)
     * - version_id (int|null)
     */
    public function getDailyTotals(int $seasonId, Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $version = EstimationBiweeklyVersion::query()
            ->where('season_id', $seasonId)
            ->where('status', EstimationVersionStatus::ACTIVE->value)
            ->orderByDesc('created_at')
            ->first();

        if (! $version) {
            return collect();
        }

        $query = DB::table('estimation_biweekly_rows as r')
            ->leftJoin('variedads as v', 'v.id', '=', 'r.variedad_id')
            ->where('r.estimation_biweekly_version_id', $version->id)
            ->whereNotNull('r.dia')
            ->whereBetween('r.dia', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['especie'])) {
            $needle = mb_strtolower(trim((string) $filters['especie']));
            $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
            $needle = preg_replace('/\s+/', ' ', (string) $needle);
            $needle = trim((string) $needle);
            $needle = preg_replace('/s$/', '', (string) $needle);
            $needle = mb_substr((string) $needle, 0, 7);
            $query->whereRaw('lower(ltrim(rtrim(coalesce(r.especie, \'\')))) like ?', ['%'.$needle.'%']);
        }

        if (! empty($filters['variedad'])) {
            $needle = mb_strtolower(trim((string) $filters['variedad']));
            $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
            $needle = preg_replace('/\s+/', ' ', (string) $needle);
            $needle = trim((string) $needle);
            $needle = mb_substr((string) $needle, 0, 12);
            $query->whereRaw('lower(ltrim(rtrim(coalesce(v.name, \'\')))) like ?', ['%'.$needle.'%']);
        }

        $rows = $query
            ->selectRaw('r.dia as dia, max(r.especie) as especie, max(v.name) as variedad, sum(coalesce(r.total_kilo,0)) as total_kilo')
            ->groupBy('r.dia')
            ->orderBy('r.dia')
            ->get();

        return collect($rows)->map(fn ($row) => [
            'dia' => (string) $row->dia,
            'especie' => $row->especie !== null ? (string) $row->especie : null,
            'variedad' => $row->variedad !== null ? (string) $row->variedad : null,
            'total_kilo' => (float) $row->total_kilo,
            'version_id' => (int) $version->id,
        ]);
    }

    /**
     * Matriz operativa: kilos estimados por día / productor / especie / variedad (nombre).
     *
     * Retorna Collection<array{dia, producer_id, producer_name, especie, variedad, kilos, version_id, mexico}>
     */
    public function getDailyKilos(int $seasonId, Carbon $from, Carbon $to, array $filters = []): Collection
    {
        // Compat: esta función ahora usa "última versión ACTIVE por bisemanal" dentro del rango.
        $weekNumbers = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        while ($cursor->lte($end)) {
            $weekNumbers[] = (int) $cursor->isoWeek();
            $cursor->addDay();
        }
        $weekVersionMap = $this->getActiveVersionIdsForWeeks($seasonId, $weekNumbers);
        $versionIds = collect($weekVersionMap)->values()->filter()->unique()->values()->all();

        if (empty($versionIds)) {
            return collect();
        }

        $query = DB::table('estimation_biweekly_rows as r')
            ->join('users as u', 'u.id', '=', 'r.producer_id')
            ->leftJoin('variedads as v', 'v.id', '=', 'r.variedad_id')
            ->whereIn('r.estimation_biweekly_version_id', $versionIds)
            ->whereNotNull('r.dia')
            ->whereBetween('r.dia', [$from->toDateString(), $to->toDateString()]);

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
            $query->whereRaw('lower(ltrim(rtrim(coalesce(r.especie, \'\')))) like ?', ['%'.$needle.'%']);
        }
        if (! empty($filters['variedad'])) {
            $needle = mb_strtolower(trim((string) $filters['variedad']));
            $needle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle) ?: $needle;
            $needle = preg_replace('/\s+/', ' ', (string) $needle);
            $needle = trim((string) $needle);
            $needle = mb_substr((string) $needle, 0, 12);
            $query->whereRaw('lower(ltrim(rtrim(coalesce(v.name, \'\')))) like ?', ['%'.$needle.'%']);
        }
        if (! empty($filters['producer_q'])) {
            $needle = '%'.mb_strtolower(trim((string) $filters['producer_q'])).'%';
            $query->whereRaw('lower(coalesce(u.name, \'\')) like ?', [$needle]);
        }

        $rows = $query
            ->selectRaw('
                r.dia as dia,
                r.estimation_biweekly_version_id as version_id,
                u.id as producer_id,
                max(u.name) as producer_name,
                max(r.especie) as especie,
                max(v.name) as variedad,
                coalesce(r.mexico, 0) as mexico,
                sum(coalesce(r.total_kilo,0)) as kilos
            ')
            ->groupBy('r.dia', 'r.estimation_biweekly_version_id', 'u.id')
            ->groupBy('r.especie', 'v.name')
            ->groupBy(DB::raw('coalesce(r.mexico, 0)'))
            ->orderBy('dia')
            ->orderBy('producer_name')
            ->get();

        // Filtrar por la versión aplicable a la semana (por si hay rangos superpuestos o versiones con data fuera de su bisemanal).
        return collect($rows)->filter(function ($row) use ($weekVersionMap) {
            $day = Carbon::parse((string) $row->dia);
            $week = (int) $day->isoWeek();
            $expected = $weekVersionMap[$week] ?? null;
            return $expected !== null && (int) $row->version_id === (int) $expected;
        })->values()->map(fn ($row) => [
            'dia' => (string) $row->dia,
            'producer_id' => (int) $row->producer_id,
            'producer_name' => (string) ($row->producer_name ?? ''),
            'especie' => $row->especie !== null ? (string) $row->especie : null,
            'variedad' => $row->variedad !== null ? (string) $row->variedad : null,
            'kilos' => (float) $row->kilos,
            'version_id' => (int) $row->version_id,
            'mexico' => ((int) ($row->mexico ?? 0)) === 1,
        ]);
    }
}
