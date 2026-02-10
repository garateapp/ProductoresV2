<?php

namespace App\Services\Planning;

use App\Models\EstimationSeason;
use App\Models\PackingProcessBatch;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProcessBatchGeneratorService
{
    public function __construct(
        private readonly ProcessGeneratorService $generator,
        private readonly BiweeklyEstimationRepositoryMysql $estimations,
    ) {
    }

    /**
     * Genera propuestas día a día para un batch, evitando repetir `n_g_recepcion`
     * dentro de la misma semana.
     */
    public function generateWeek(PackingProcessBatch $batch): array
    {
        $batch->loadMissing(['processes']);

        $from = Carbon::parse($batch->week_start)->startOfDay();
        $to = Carbon::parse($batch->week_end)->endOfDay();

        $seasonId = $this->resolveSeasonIdForRange($from, $to);
        $estRows = $seasonId > 0
            ? $this->estimations->getDailyKilos($seasonId, $from, $to, ['only_active_producers' => true])
            : collect();

        /**
         * Map: day -> especieNeedle -> ['kilos' => float, 'variedades' => array<string,true>]
         *
         * Note: la estimación viene por productor; agregamos a nivel especie+variedad (y México queda en el rowKey
         * del flujo, pero para la propuesta semanal usamos total por especie del día y restringimos a variedades estimadas).
         */
        $estMap = [];
        foreach ($estRows as $r) {
            $day = (string) ($r['dia'] ?? '');
            if ($day === '') {
                continue;
            }

            $esNeedle = $this->normalizeSpeciesNeedle((string) ($r['especie'] ?? ''));
            if ($esNeedle === '') {
                continue;
            }

            $varKey = $this->normalizeVarietyKey((string) ($r['variedad'] ?? ''));
            $kilos = (float) ($r['kilos'] ?? 0);
            if ($kilos <= 0) {
                continue;
            }

            $estMap[$day][$esNeedle] = $estMap[$day][$esNeedle] ?? ['kilos' => 0.0, 'variedades' => []];
            $estMap[$day][$esNeedle]['kilos'] += $kilos;
            if ($varKey !== '') {
                $estMap[$day][$esNeedle]['variedades'][$varKey] = true;
            }
        }

        $used = [];
        $results = [];

        foreach ($batch->processes->sortBy([['fecha', 'asc'], ['especie', 'asc'], ['id', 'asc']])->values() as $process) {
            $day = optional($process->fecha)->toDateString();
            $esNeedle = $this->normalizeSpeciesNeedle((string) $process->especie);

            $targetKilos = 0.0;
            $allowedVariedades = [];

            if ($day && $esNeedle !== '' && isset($estMap[$day][$esNeedle])) {
                $targetKilos = (float) ($estMap[$day][$esNeedle]['kilos'] ?? 0.0);
                $allowedVariedades = array_keys((array) ($estMap[$day][$esNeedle]['variedades'] ?? []));
            }

            $this->generator->generate($process, [
                'exclude_n_g_recepcion' => $used,
                // Generar en base a lo estimado (cap de kilos). Si no hay estimación, queda sin lotes.
                'max_kilos' => $targetKilos,
                // Solo considerar variedades presentes en la estimación del día (si no hay, se permite todo).
                'allowed_variedades' => $allowedVariedades,
            ]);

            $process->loadMissing('lots');
            $newUsed = $process->lots->pluck('n_g_recepcion')->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all();
            $used = array_values(array_unique(array_merge($used, $newUsed)));

            $results[] = [
                'process_id' => $process->id,
                'fecha' => optional($process->fecha)->toDateString(),
                'lots' => (int) $process->lots()->count(),
            ];
        }

        return [
            'ok' => true,
            'processes' => $results,
            'used_count' => count($used),
        ];
    }

    private function resolveSeasonIdForRange(Carbon $from, Carbon $to): int
    {
        $seasonId = (int) (EstimationSeason::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $from->toDateString())
            ->where('end_date', '>=', $to->toDateString())
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        if ($seasonId <= 0) {
            $seasonId = (int) (EstimationSeason::query()->orderByDesc('is_active')->orderByDesc('id')->value('id') ?? 0);
        }

        return $seasonId;
    }

    private function normalizeSpeciesNeedle(string $value): string
    {
        $s = trim($value);
        if ($s === '') {
            return '';
        }
        $s = mb_strtolower($s);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/\s+/', ' ', (string) $s);
        $s = trim((string) $s);
        if (strlen($s) > 4) {
            $s = preg_replace('/s$/', '', (string) $s);
        }
        $s = mb_substr((string) $s, 0, 7);
        return (string) $s;
    }

    private function normalizeVarietyKey(string $value): string
    {
        $s = trim($value);
        if ($s === '') {
            return '';
        }
        $s = mb_strtolower($s);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/\s+/', ' ', (string) $s);
        $s = trim((string) $s);
        return (string) $s;
    }
}
