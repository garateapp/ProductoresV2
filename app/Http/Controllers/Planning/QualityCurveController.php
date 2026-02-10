<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planning\Concerns\AuthorizesPlanning;
use App\Models\Recepcion;
use App\Services\QualityChartsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QualityCurveController extends Controller
{
    use AuthorizesPlanning;

    public function sizeDistribution(Request $request)
    {
        $this->authorizePlanning($request);

        $validated = $request->validate([
            'n_g_recepcions' => ['required', 'array', 'min:1', 'max:30'],
            'n_g_recepcions.*' => ['string'],
            'refresh' => ['sometimes', 'boolean'],
        ]);

        $ids = collect($validated['n_g_recepcions'])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
        Log::debug('Size distribution request for receptions', ['ids' => $ids]);
        if (empty($ids)) {
            return response()->json(['data' => []]);
        }

        $cacheKeys = [];
        foreach ($ids as $n) {
            $cacheKeys[$n] = 'planning:size_distribution:'.$n;
        }

        $refresh = (bool) ($validated['refresh'] ?? false);
        $cached = $refresh ? [] : Cache::many(array_values($cacheKeys));
        Log::debug('Size distribution cached data', ['refresh' => $refresh, 'data' => $cached]);
        $missing = [];
        $out = [];
        foreach ($ids as $n) {
            $key = $cacheKeys[$n];
            if (isset($cached[$key]) && is_array($cached[$key])) {
                $out[$n] = $cached[$key];
            } else {
                $missing[] = $n;
            }
        }
        Log::debug('Size distribution missing data', ['ids' => $missing]);

        if (! empty($missing)) {
            $recepciones = Recepcion::query()
                ->whereIn('numero_g_recepcion', $missing)
                ->with(['calidad.detalles'])
                ->get()
                ->keyBy(fn ($r) => (string) $r->numero_g_recepcion);

            foreach ($missing as $n) {
                $reception = $recepciones->get($n);
                $payload = $this->computeSizeDistributionPayload($reception ? collect([$reception]) : collect());
                $out[$n] = $payload;

                $ttlMinutes = 30;
                if (($payload['type'] ?? '') === 'calibres' && is_array($payload['data'] ?? null) && empty($payload['data'])) {
                    // Si está vacío, puede ser por falta temporal de vinculación/calidad.
                    // No lo cacheamos tanto para permitir que aparezca cuando el dato se cargue.
                    $ttlMinutes = 5;
                }

                Cache::put($cacheKeys[$n], $payload, now()->addMinutes($ttlMinutes));
            }
        }
        Log::debug('Size distribution computed data', ['data' => $out]);

        return response()->json(['data' => $out]);
    }

    /**
     * @param \Illuminate\Support\Collection<int, mixed> $receptions
     * @return array{type:string,data:mixed}
     */
    private function computeSizeDistributionPayload(Collection $receptions): array
    {
        $raw = QualityChartsService::getSizeDistributionData($receptions);
        Log::debug('Size distribution raw data', ['data' => $raw]);
        if (isset($raw['categories']) && isset($raw['series'])) {
            return [
                'type' => 'cherries',
                'data' => $raw,
            ];
        }

        return [
            'type' => 'calibres',
            'data' => is_array($raw) ? array_values($raw) : [],
        ];
    }
}
