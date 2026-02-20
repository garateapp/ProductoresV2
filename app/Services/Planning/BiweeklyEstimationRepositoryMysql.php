<?php

namespace App\Services\Planning;

use App\Enums\EstimationVersionStatus;
use App\Models\EstimationBiweeklyVersion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BiweeklyEstimationRepositoryMysql
{
    public const ORIGIN_AGRONOMO = 'agronomo';

    public const ORIGIN_SERVICE_PLANNER = 'servicio_planificador';

    /**
     * @param  string  $origin  agronomo|servicio_planificador
     */
    public function getActiveVersionId(int $seasonId, string $origin = self::ORIGIN_AGRONOMO): ?int
    {
        return EstimationBiweeklyVersion::query()
            ->where('season_id', $seasonId)
            ->where('origin', $origin)
            ->where('status', EstimationVersionStatus::ACTIVE->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');
    }

    /**
     * Compat: devuelve 1 versión por semana priorizando origen agrónomo.
     *
     * @param  array<int>  $weekNumbers
     * @return array<int,int|null> map semana => version_id
     */
    public function getActiveVersionIdsForWeeks(int $seasonId, array $weekNumbers): array
    {
        $byOrigin = $this->getActiveVersionIdsForWeeksByOrigin($seasonId, $weekNumbers, [
            self::ORIGIN_AGRONOMO,
        ]);

        $map = [];
        foreach ($byOrigin as $week => $origins) {
            $map[(int) $week] = $origins[self::ORIGIN_AGRONOMO] ?? null;
        }

        return $map;
    }

    /**
     * Devuelve versión ACTIVE aplicable por semana y por origen.
     *
     * @param  array<int>  $weekNumbers
     * @param  array<int,string>  $origins
     * @return array<int,array<string,int|null>> map semana => [origin => version_id|null]
     */
    public function getActiveVersionIdsForWeeksByOrigin(int $seasonId, array $weekNumbers, array $origins = [self::ORIGIN_AGRONOMO, self::ORIGIN_SERVICE_PLANNER]): array
    {
        $weeks = collect($weekNumbers)
            ->map(fn ($w) => (int) $w)
            ->filter(fn ($w) => $w >= 1 && $w <= 53)
            ->unique()
            ->values();

        $originList = collect($origins)
            ->map(fn ($origin) => trim((string) $origin))
            ->filter(fn ($origin) => $origin !== '')
            ->unique()
            ->values();

        if ($weeks->isEmpty() || $originList->isEmpty()) {
            return [];
        }

        $actives = EstimationBiweeklyVersion::query()
            ->where('season_id', $seasonId)
            ->where('status', EstimationVersionStatus::ACTIVE->value)
            ->whereIn('origin', $originList->all())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'origin', 'period_start_week', 'period_end_week', 'created_at']);

        $fallbackByOrigin = [];
        foreach ($originList as $origin) {
            $fallbackByOrigin[$origin] = $actives->firstWhere('origin', $origin);
        }

        $map = [];
        foreach ($weeks as $week) {
            $map[$week] = [];

            foreach ($originList as $origin) {
                $picked = null;
                foreach ($actives as $version) {
                    if ((string) $version->origin !== (string) $origin) {
                        continue;
                    }

                    $start = (int) ($version->period_start_week ?? 1);
                    $end = (int) ($version->period_end_week ?? 53);
                    if ($week >= $start && $week <= $end) {
                        $picked = (int) $version->id;
                        break;
                    }
                }

                $map[$week][$origin] = $picked
                    ?? (isset($fallbackByOrigin[$origin]) && $fallbackByOrigin[$origin]
                        ? (int) $fallbackByOrigin[$origin]->id
                        : null);
            }
        }

        return $map;
    }

    /**
     * Devuelve kilos estimados por día (y opcionalmente por variedad) usando la versión ACTIVE más reciente
     * de agrónomos para la temporada indicada.
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
        $versionId = $this->getActiveVersionId($seasonId, self::ORIGIN_AGRONOMO);
        if (! $versionId) {
            return collect();
        }

        $query = DB::table('estimation_biweekly_rows as r')
            ->leftJoin('variedads as v', 'v.id', '=', 'r.variedad_id')
            ->where('r.estimation_biweekly_version_id', $versionId)
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
            'version_id' => (int) $versionId,
        ]);
    }

    /**
     * Matriz operativa: kilos estimados por día / productor / especie / variedad (nombre).
     *
     * Combina estimaciones de agrónomos + estimaciones manuales de servicios.
     *
     * Retorna Collection<array{dia, producer_id, producer_name, especie, variedad, kilos, version_id, mexico, origin}>
     */
    public function getDailyKilos(int $seasonId, Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $weekNumbers = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        while ($cursor->lte($end)) {
            $weekNumbers[] = (int) $cursor->isoWeek();
            $cursor->addDay();
        }

        $weekVersionMapByOrigin = $this->getActiveVersionIdsForWeeksByOrigin($seasonId, $weekNumbers, [
            self::ORIGIN_AGRONOMO,
            self::ORIGIN_SERVICE_PLANNER,
        ]);

        $versionIds = collect($weekVersionMapByOrigin)
            ->flatMap(fn ($map) => collect($map)->values())
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($versionIds)) {
            return collect();
        }

        $query = DB::table('estimation_biweekly_rows as r')
            ->join('estimation_biweekly_versions as vb', 'vb.id', '=', 'r.estimation_biweekly_version_id')
            ->join('users as u', 'u.id', '=', 'r.producer_id')
            ->leftJoin('variedads as v', 'v.id', '=', 'r.variedad_id')
            ->whereIn('r.estimation_biweekly_version_id', $versionIds)
            ->whereNotNull('r.dia')
            ->whereBetween('r.dia', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['only_active_producers'])) {
            // Para origen "servicio_planificador" no filtramos por is_active del dueño del servicio,
            // porque son cargas manuales del planificador y deben siempre impactar planificación.
            $query->where(function ($scoped) {
                $scoped
                    ->where('vb.origin', self::ORIGIN_SERVICE_PLANNER)
                    ->orWhere(function ($nested) {
                        $nested
                            ->where('vb.origin', '!=', self::ORIGIN_SERVICE_PLANNER)
                            ->where('u.is_active', true);
                    });
            });
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
                vb.origin as origin,
                u.id as producer_id,
                max(u.name) as producer_name,
                max(r.especie) as especie,
                max(v.name) as variedad,
                coalesce(r.mexico, 0) as mexico,
                sum(coalesce(r.total_kilo,0)) as kilos
            ')
            ->groupBy('r.dia', 'r.estimation_biweekly_version_id', 'vb.origin', 'u.id')
            ->groupBy('r.especie', 'v.name')
            ->groupBy(DB::raw('coalesce(r.mexico, 0)'))
            ->orderBy('dia')
            ->orderBy('producer_name')
            ->get();

        return collect($rows)->filter(function ($row) use ($weekVersionMapByOrigin) {
            $day = Carbon::parse((string) $row->dia);
            $week = (int) $day->isoWeek();
            $origin = trim((string) ($row->origin ?? ''));
            if ($origin === '') {
                return false;
            }

            $expected = $weekVersionMapByOrigin[$week][$origin] ?? null;
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
            'origin' => (string) ($row->origin ?? ''),
        ]);
    }
}
